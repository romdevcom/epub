<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ElibriAPI extends Controller
{
    private $host = "https://staging.elibri.com.ua";
    private $login;
    private $password;
    private $uriPrefix = "/api/v1/";

    private $_Q_POP ="queues/%s/pop";
    private $_Q_REFILL = "queues/refill_all";
    private $_Q = "queues";
    private $_Q_LASTPOP = "queues/%s/last_pop";
    private $_P = "publishers";
    private $_P_PRODUCTS = "publishers/%s/products";
    private $_P_REFERENCE = "products/%s";

    private $_POST = "POST";
    private $_GET = "GET";

    private $curlHeader = "X-eLibri-API-ONIX-dialect: 3.0.1";

    //! Nazwa kolejki z danymi o dostępności produktów
    const STOCKS_QUEUE = "stocks";

    //! Nazwa kolejki z metadanymi produktów
    const META_QUEUE = "meta";

    //! Kontruktor obiektu API
    function __construct($login, $password, $host=NULL) {

        $this->login = $login;
        $this->password = $password;
        if (isset($host)) $this->host = $host;

    }

    private function parse_headers($headers) {
        $dict = Array();
        $lines = explode("\n", $headers);
        foreach($lines as $line) {
            if ((trim($line)) && strrpos($line, ":")) {
                $spl = explode(":", trim($line));
                $header = $spl[0];
                $value = trim($spl[1]);
                $dict[$header] = $value;
            }
        }
        return $dict;
    }

    private function request($command, $method, $param=NULL, $query=NULL) {

        $uri = $this->host . $this->uriPrefix;
        if (isset($param)) {
            $uri .= sprintf($command, $param);

        } else {
            $uri .= $command;
        }
        if (isset($query)) {
            $uri = "$uri?$query";
        }
        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array($this->curlHeader));
        curl_setopt($ch, CURLOPT_USERPWD, $this->login.":".$this->password);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);

        if ($method == $this->_GET) {
            curl_setopt($ch, CURLOPT_HTTPGET, 1);
        } else {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, "");
        }

        $curlResult = curl_exec($ch);
        if ($curlResult === FALSE) {
            throw new ElibriDataAPIConnectionException (curl_error($ch), curl_errno($ch));
        }
        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response_code == 404) {
            throw new ElibriDataAPINotFoundException();
        } else if ($response_code == 403) {
            throw new ElibriDataAPIForbiddenException();
        } else if ($response_code == 500) {
            throw new ElibriDataAPIServerErrorException();
        } else if ($response_code == 401) {
            throw new ElibriDataAPIInvalidAuthException();
        } else if (($response_code != 200) && ($response_code != 412)) {
            throw new ElibriDataAPIUnknownException();
        }

        $headers_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = $this->parse_headers(trim(substr($curlResult, 0, $headers_size)));
        $curlResult = trim(substr($curlResult, $headers_size -1));
        curl_close($ch);
        if (!$curlResult) { #zwrócony został pusty string
            return Array($headers, $curlResult);
        }

        $xmlObject = simplexml_load_string($curlResult);
        $json = json_encode($xmlObject);
        $results = json_decode($json, true);
        //dd($results);
//        $xml = new XMLReader();
//        $xml->XML($curlResult);
//        $xml->read();

//        if ($xml->localName == "error") {
//            $nr = $xml->getAttribute("id");
//
//            switch ($nr) {
//                case '1002': throw new ElibriDataAPINoPoppedDataException(); break;
//                case '1003': throw new ElibriDataAPIInvalidDialectException(); break;
//                default: throw new ElibriDataAPIUnknownException(); break;
//            }
//        }

//        $xml->close();
        return Array($headers, $results);
    }

    //! Pobierz listę wydawnict obecnych w eLibri
    function getPublishersList() {

        $data = $this->request($this->_P, $this->_GET);
        $source = $data[1];
        return ElibriPublisherInfo::parse($source);
    }

    //! @brief Pobierz listę produktów należących do wydawnictwa
    //! @param int $publisherId - ID wydawnictwa
    //! @return lista instancji ElibriPublisherProduct
    function getPublisherProducts($publisherId) {

        $data = $this->request($this->_P_PRODUCTS, $this->_GET, $publisherId);
        $source = $data[1];
        return ElibriPublisherProduct::parse($source);
    }

    //! @brief pobierz listę kolejek danych
    //! @return lista instancji ElibriQueue
    function getQueues() {
        $data = $this->request($this->_Q, $this->_GET);
        $source = $data[1];
        return ElibriQueue::parse($source);
    }

    //! @brief Pobierz dane z kolejki
    //! @param String $queue - nazwa kolejki
    //! @param int $count - ilość produktów do pobrania, max. 100 (domyślnie 30)
    //! @param bool $testing - jeśli true, to dane nie są usuwane z kolejki (domyślnie false)
    //! @return instancja ElibriOnixMessage albo NULL, jeśli kolejka danych jest pusta
    function popQueue($queue, $count = 30, $testing = false) {
        global $xml;
        if (($queue != "meta") && ($queue != "stocks")) {
            throw new ElibriDataAPIInvalidQueueException();
        }
        $testing = (int) $testing;
        $query = "count=$count&amp;testing=$testing";
        $data = $this->request($this->_Q_POP, $this->_POST, $queue, $query);
        $headers = array_change_key_case($data[0], CASE_LOWER);
        $source = $data[1];
        if ($headers["x-elibri-api-pop-products-count"] == 0) {
            return NULL;
        } else {
            //$xml =  "$source\n\n\n";
            return $source;
        }
    }

    //! @brief zapełnij kolejki wszystkimi dostępnymi danymi
    function refillAll() {
        $this->request($this->_Q_REFILL, $this->_POST);
    }

    //! @brief Ponów pobieranie danych z kolejki.
    //! Metoda ta może zostać użyta, gdy podczas pobierania danych z kolejki wystąpił jakiś błąd
    //! @param String $queue - nazwa kolejki
    //! @return instancja ElibriOnixMessage
    function lastPopQueue($queue) {
        $data = $this->request($this->_Q_LASTPOP, $this->_GET, $queue);
        return ElibriOnixMessage::parse($data[1]);
    }

    //! @brief Pobierz metadane jednego produktu
    //! @param $reference - record_reference produktu, który ma zostać pobrany
    //! @return instancja ElibriOnixMessage - w tablicy $products jest tylko jeden produkt
    function getProduct($reference) {
        $data = $this->request($this->_P_REFERENCE, $this->_GET, $reference);
        return ElibriOnixMessage::parse($data[1]);
    }
}
