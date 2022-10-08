<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request){

        $seo = [];
        $seo['title'] = 'Books';
        return view('books', compact('seo'));
    }
}
