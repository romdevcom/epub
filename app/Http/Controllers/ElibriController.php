<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\ElibriAPI;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ElibriController extends Controller
{
    private string $host = 'https://platform.elibri.com.ua';
    private string $login = 'info@epub.com.ua';
    private string $password = 'baabcf2368004e0bee5d';
    private array $types = ['AUDIO_DOWNLOADABLE_FILE', 'EBOOK'];

    public function test(){
        $product = Product::get()->first();
        dd('http://epub.local' . Storage::url($product->image));
    }

    public function index(){
        $api = new ElibriAPI($this->login, $this->password, $this->host);
        $api->refillAll();
        $message = $api->popQueue('meta', 2);
        foreach ($message['Product'] as $product){
            echo $this->product_import($product) . '<br>';
        }
    }

    //$product['RecordReference'] - ref
    //$product['ProductIdentifier']['IDValue'] - isbn
    //$product['ProductSupply']['SupplyDetail']['Price']['PriceAmount'] - price
    //$product['ProductSupply']['SupplyDetail']['Supplier']['SupplierName'] - publisher
    //$product['DescriptiveDetail']['TitleDetail'][0]['TitleElement']['TitleText'] - title

    //$product['DescriptiveDetail']['Contributor'] - persons
    //$product['DescriptiveDetail']['Contributor']['ContributorRole'] - roles
    //A01 - автор
    //B06 - перекладач

    //$product['DescriptiveDetail']['Language'] - languages
    //$product['DescriptiveDetail']['Language']['LanguageCode'] - ukr,rus,eng

    //$product['CollateralDetail']['SupportingResource'] - посилання на файли (зображення, семпли)
    //$product['CollateralDetail']['SupportingResource'][...]['ResourceContentType'] - ролі (01 - зображення, 15 - семпл)
    //$product['CollateralDetail']['SupportingResource'][...]['ResourceVersion']['ResourceLink'] - посилання
    //$product['CollateralDetail']['SupportingResource'][...]['ResourceVersion']['ContentDate']['Date'] - дата додавання зображення
    //$product['CollateralDetail']['SupportingResource'][...]['ResourceVersion'][...] - семпли будуть в масиві
    public function product_import($product){
        $existed = Product::where('ref', $product['RecordReference'])->get()->first();
        if(empty($existed)){
            $new = new Product;
            $new->ref = $product['RecordReference'];

            //titles
            if(isset($product['DescriptiveDetail']['TitleDetail'][0]['TitleElement']['TitleText'])){
                $title = $product['DescriptiveDetail']['TitleDetail'][0]['TitleElement']['TitleText'];
                $new->name = $title;
            }else{
                foreach($product['DescriptiveDetail']['TitleDetail'][0]['TitleElement'] as $element){
                    if($element['TitleElementLevel'] == '01'){
                        $title = $element['TitleText'];
                        $new->name = $title;
                    }
                }
            }
            $new->slug = Str::slug($title);

            //isbn
            if(is_array($product['ProductIdentifier']) && !isset($product['ProductIdentifier']['ProductIDType'])){
                foreach($product['ProductIdentifier'] as $identifier){
                    if($identifier['ProductIDType'] == '15'){
                        $new->isbn = $identifier['IDValue'];
                    }
                }
            }else{
                $new->isbn = $product['ProductIdentifier']['IDValue'];
            }

            //price
            $new->price = $product['ProductSupply']['SupplyDetail']['Price']['PriceAmount'];
            $new->languages = $product['DescriptiveDetail']['Language']['LanguageCode'];

            //resources
            if(isset($product['CollateralDetail']['SupportingResource'])){
                foreach($product['CollateralDetail']['SupportingResource'] as $resource){
                    if($resource['ResourceContentType'] == '01'){
                        $new->image = $this->save_image($resource['ResourceVersion']['ResourceLink'], $product['RecordReference']);
                        $new->image_date = $resource['ResourceVersion']['ContentDate']['Date'];
                    }
                }
            }
            $new->save();
            return $title;
        }
        return 'false';
    }

    public function save_image($url, $ref){
        $contents = file_get_contents($url);
        $name = 'public/books/' . $ref . '-' . substr($url, strrpos($url, '/') + 1);
        $image = Storage::put($name, $contents);
        return $image ? $name : '';
    }
}
