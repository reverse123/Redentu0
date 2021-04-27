<?php
namespace App\Imports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray; // https://docs.laravel-excel.com/3.1/imports/concerns.html

class ProductsImport implements ToArray
{
    // розмір буфера для додавання в базу
    const chunk_size = 500;
    // результати виконання імпорту
    public $imported_total = 0,
           $imported_new = 0,
           $imported_ignored = 0;

    public function array(array $array)
    {
        // пропускаєм заголовок
        unset($array[0]);

        // форматування імпортованих даних (видалення пропусків та зсувів)
        foreach ($array as $k => $row) {
            // якщо перша колонка порожня та колонка ціни не на свому місці то зсунути елементи вліво
            // (фікс зсуву зі строки 5612)
            if (!$row[0] && !is_numeric($row[7]) && is_numeric($row[8]))
                array_shift($row);
            // масив для категорій
            $cats = [$row[0], $row[1], $row[2]];
            // якщо перша категорія порожня то зсунути елементи вліво, до двох рівнів
            if (!$cats[0]) {
                array_shift($cats);
                $cats[2] = null;
            }
            if (!$cats[0]) {
                array_shift($cats);
                $cats[2] = null;
            }
            // видаляємо категорії з початкової строки,
            unset($row[0], $row[1], $row[2]);
            // ...і об"єднуємо з масивом для категорій
            $array[$k] = array_merge($cats,$row);
        }

        /*
         *  Робота з категоріями
         * */
        // масиви для зберігання id категорій за їх назвою
        $categories0 = [];
        $categories1 = [];
        $categories2 = [];
        // запис унікальних категорій всіх рівнів для подальшої вставки у БД
        // (це можна робити колекціями, але так працює швидше)
        foreach ($array as $row) {
            if($row[0]) $categories0[$row[0]] = 0;
            if($row[1]) $categories1[$row[1]] = 0;
            if($row[2]) $categories2[$row[2]] = 0;
        }
        // вибираєм існуючі категорії, щоб не вставляти їх, та записуєм знайдені id в categories
        $exist_categories0 = DB::table('category0')->select('id','name')->whereIn('name', array_keys($categories0))->pluck('id','name')->toArray();
        $categories0 = array_merge($categories0, $exist_categories0);
        // те саме для категорій інших рівнів
        $exist_categories1 = DB::table('category1')->select('id','name')->whereIn('name', array_keys($categories1))->pluck('id','name')->toArray();
        $categories1 = array_merge($categories1, $exist_categories1);

        $exist_categories2 = DB::table('category2')->select('id','name')->whereIn('name', array_keys($categories2))->pluck('id','name')->toArray();
        $categories2 = array_merge($categories2, $exist_categories2);

        foreach ($categories0 as $category_name => $category_id) {
            // якщо категорії не було в базі
            if(!$category_id) // то вставити її та отримати id
                $categories0[$category_name] = DB::table('category0')->insertGetId(['name' => $category_name]);
            //$categories0[$category_name] = DB::getPdo()->lastInsertId();
        }
        // те саме для категорій інших рівнів
        foreach ($categories1 as $category_name => $category_id) {
            if(!$category_id)
                $categories1[$category_name] = DB::table('category1')->insertGetId(['name' => $category_name]);
        }
        foreach ($categories2 as $category_name => $category_id) {
            if(!$category_id)
                $categories2[$category_name] = DB::table('category2')->insertGetId(['name' => $category_name]);
        }
        DB::commit();
        /*
         *  Кінець роботи з категоріями. В результаті маємо масиви з унікальними категоріями та їх id, формат [category_name=>category_id]
         * */

        $insert_records = [];
        $last_key = array_key_last($array);
        $this->imported_total = 0;
        $this->imported_new = 0;
        $this->imported_ignored = 0;
        foreach ($array as $k => $row) {
            $insert_records []= ['category0' => $categories0[$row[0]] ?? null, 'category1' => $categories1[$row[1]] ?? null, 'category2' => $categories2[$row[2]] ?? null,
                                 'manufacturer' => $row[3], 'model_code' => $row[5], 'description' => $row[4],
                                 'price' => (int)$row[7], 'warranty' => (int)$row[8], 'available' => $row[9], 'debug_key' => $k + 1];

            // додавання буфера в базу
            if(count($insert_records) > self::chunk_size || $k === $last_key) {
                $inserted_rows = DB::table('products')->insertOrIgnore($insert_records);
                $this->imported_total += count($insert_records);
                $this->imported_new += $inserted_rows;
                $this->imported_ignored += count($insert_records) - $inserted_rows;

                $insert_records = [];
            }
        }
        DB::commit();
    }
}
