<?php

    define("IN_SITE", true);
    require_once(__DIR__.'/../libs/db.php');
    require_once(__DIR__.'/../config.php');
    require_once(__DIR__.'/../libs/helper.php');
    require_once(__DIR__.'/../libs/lang.php');
    $CMSNT = new DB();


    
    if($CMSNT->site('pin_cron') != ''){
        if(empty($_GET['pin'])){
            die('Vui lòng nhập mã PIN');
        }
        if($_GET['pin'] != $CMSNT->site('pin_cron')){
            die('Mã PIN không chính xác');
        }
    }

    /* START CHỐNG SPAM */
    if (time() > $CMSNT->site('check_time_cron7')) {
        if (time() - $CMSNT->site('check_time_cron7') < 5) {
            die('Thao tác quá nhanh, vui lòng đợi');
        }
    }


    $CMSNT->update("settings", [
        'value' => time()
    ], " `name` = 'check_time_cron7' ");

    // LẤY SẢN PHẨM TỪ API VỀ
    if($CMSNT->site('status_connect_api') == 1){
        // LẤY DANH SÁCH WEBSITE API
        foreach($CMSNT->get_list(" SELECT * FROM `connect_api` WHERE `status` = 1 AND `type` = 'API_7' ") as $website){


            // CURL LẤY CATEGORIES
            $data = listProduct_API_7($website['domain'], $website['password']);
            $data = json_decode($data, true);
            foreach($data['success'] as $account){
                $product_name = check_string($account['name']);
                $amount = check_string($account['amount']);
                $api_stock = check_string($account['product_count']);
                $ck = check_string($amount) * $website['ck_connect_api'] / 100;
                $price = check_string($amount) + $ck;
                if(!$rowProduct = $CMSNT->get_row(" SELECT * FROM `products` WHERE `id_api` = '".check_string($account['id'])."' AND `id_connect_api` = '".$website['id']."' ")){
                    // LẤY ID CATEGORY
                    $id_api = 0;
                    $isInsert = $CMSNT->insert('products', [
                        'user_id'           => $website['user_id'],
                        'category_id'       => $id_api,
                        'id_api'            => check_string($account['id']),
                        'id_connect_api'    => $website['id'],
                        'name'              => $product_name,
                        'name_api'          => $product_name,
                        'price'             => $price,
                        'status'            => $CMSNT->site('default_api_product_status'),
                        'cost'              => check_string($amount),
                        'api_stock'         => $api_stock,
                        'flag'              => '',
                        'content'           => '',
                        'update_api'        => time(),
                        'minimum'           => 1,
                        'maximum'           => 10000
                    ]);
                    if($isInsert){
                        echo '<b style="color:red;">CREATE</b> - Tạo sản phẩm '.$product_name.' thành công !<br>';
                    }
                }else{
                    $price = $rowProduct['price'];
                    if($website['status_update_ck'] == 1){
                        $ck = $amount * $website['ck_connect_api'] / 100;
                        $price = $amount + $ck;
                    }
                    $product_name = $rowProduct['name'];
                    if($website['auto_rename_api'] == 1){
                        $product_name = check_string($account['name']);
                    }
                    // CẬP NHẬT GIÁ VÀ SỐ LƯỢNG SẢN PHẨM API
                    $isUpdate = $CMSNT->update('products', [
                        'price'         => $price,
                        'name'          => $product_name,
                        'name_api'      => check_string($account['name']),
                        'api_stock'     => $api_stock,
                        'update_api'    => time(),
                        'cost'          => check_string($amount)
                    ], " `id` = '".$rowProduct['id']."' ");
                    if($isUpdate){
                        echo '<b style="color:green;">UPDATE</b> - sản phẩm '.$product_name.' thành công !<br>';
                    }
                }
            }
            

            // ẨN SẢN PHẨM KHI API XOÁ HOẶC ẨN SẢN PHẨM
            $CMSNT->remove('products', " `id_connect_api` = '".$website['id']."' AND ".time()." - `update_api` >= 3600 ");
        }
    }