<?php

namespace App\Controllers;
use App\Imports\ProductsImport;
use App\Rules\MaxUploadFilesize;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;


class ImportController
{
    public function createForm() {
        return view('file-upload');
    }

    public function fileUpload(Request $req) {
        $req->validate([
            // також можна використовувати max:10000 для валідації розміру
            'file' => ['bail','required','mimes:xlsx,xls', new MaxUploadFilesize()]
        ]);

        $products_import = new ProductsImport;
        Excel::import($products_import, $req->file('file'));

        return back()->with('success', "Excel файл успішно імпортовано. Опрацьовано записів: " . $products_import->imported_total .
            "; з них імпортовано: " . $products_import->imported_new .
            "; пропущено (така модель вже була в базі): " . $products_import->imported_ignored);
    }
}
