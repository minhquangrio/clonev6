<?php

$envFilePath = '.env';

// Đọc nội dung của tệp .env
$envContent = file_get_contents($envFilePath);

// Chuyển đổi nội dung thành mảng các cặp key-value
$envVariables = explode("\n", $envContent);

// Tạo một mảng để lưu trữ các giá trị cấu hình
$config = [];

// Lặp qua mảng và đưa các giá trị vào mảng cấu hình
foreach ($envVariables as $envVariable) {
    // Chia chuỗi thành key và value
    list($key, $value) = explode('=', $envVariable, 2);

    // Lọc bỏ khoảng trắng
    $key = trim($key);
    $value = trim($value);

    // Lưu vào mảng cấu hình
    $config[$key] = $value;
}

// Sử dụng các giá trị cấu hình để kết nối database
$to9xvn_local = $config['DB_HOST'];
$to9xvn_ten = $config['DB_USERNAME'];
$to9xvn_matkhau = $config['DB_PASSWORD'];
$to9xvn_dulieu = $config['DB_DATABASE'];

$ketnoi = @mysqli_connect($to9xvn_local, $to9xvn_ten, $to9xvn_matkhau, $to9xvn_dulieu) or die("to9xvn: Thông tin kết nối dữ liệu không chính xác");
@mysqli_set_charset($ketnoi, "utf8");
// code được viết bởi to9xvn
// trang web: http://dailysieure.com/
// liên hệ: http://dailysieure.com/to9xvn
// Vui lòng không xoá và tôn trọng tác giả làm ra nó
$MEMO_PREFIX = 'NAPVND';
function parse_order_id($des){
    global $MEMO_PREFIX;
    $re = '/'.$MEMO_PREFIX.'\d+/im';
    preg_match_all($re, $des, $matches, PREG_SET_ORDER, 0);
    if (count($matches) == 0 )
        return null;
    // Print the entire match result
    $orderCode = $matches[0][0];
    $prefixLength = strlen($MEMO_PREFIX);
    $orderId = intval(substr($orderCode, $prefixLength ));
    return $orderId ;
}
function curl_get_api_dailysieure($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $data = curl_exec($ch);
    
    curl_close($ch);
    return $data;
}


$urlapi = 'https://'.$_SERVER['HTTP_HOST'].'/mbbank/index.php'; // link check lịch sử giao dịch của bạn
$ketquacurl = curl_get_api_dailysieure($urlapi);

$curl = curl_init();
//$proxy = '';
$proxy = explode(":", $proxy);
curl_setopt_array($curl, array(
    CURLOPT_RETURNTRANSFER => 1,
  CURLOPT_URL => $urlapi,
    CURLOPT_USERAGENT => 'DAILYSIEURE',
   // CURLOPT_POST => 1,
CURLOPT_TIMEOUT => 5, 
//CURLOPT_PROXY => $proxy[0].":".$proxy[1],
 //CURLOPT_PROXYUSERPWD => $proxy[2].":".$proxy[3],
    CURLOPT_SSL_VERIFYPEER => false, //Bỏ kiểm SSL
   
));
$ketquacurl = curl_exec($curl);
$result = str_replace('transactionHistoryList', 'transactions', $ketquacurl);
$result = str_replace('refNo', 'transactionID', $result);
$ketquacurl = str_replace('creditAmount', 'amount', $result);
curl_close($curl);


    $time = time();
    $sqlFormattedDateTime = date('Y-m-d H:i:s');
//exit("ma no cay $urlapi<br> ".$ketquacurl);
$ketqua = json_decode($ketquacurl, true);
if($ketqua['status'] == '1'){
    echo $ketqua['msg'] ;
}else{
 //  echo $ketquacurl; 
  $checkGD = @json_decode($ketquacurl)->transactions;
if ($checkGD == null){  
 echo 'Không có lịch sử gd nào trong hôm nay hoặc sai số tài khoản';   
}else{
   // lịch sử giao dịch đã xuất hiện
   foreach(array_reverse($checkGD) as $struct) {
    $tien = $struct->amount; // số tiền người ta gửi
     $tien = str_replace(',', '', $tien);
    $noidung = $struct->description; // nội dung gửi tiền
     $noidung =  htmlspecialchars(mysqli_real_escape_string($ketnoi, $noidung));
     $noidung=strtolower($noidung); // strtolower
    $magd = $struct->transactionID; // mã giao dịch
        $magd =  htmlspecialchars(mysqli_real_escape_string($ketnoi, $magd));
    $mang_ket_qua = explode('\\', $magd);
    $magd = $mang_ket_qua[0];
     $type  = $struct->type;
    // giờ code để cho nó cộng tiền hay gì tuỳ bạn thôi
 //echo '<br>So tien '.$tien.' Ma GD '.$magd; exit;

$tien = abs($tien);
$idnhantien = parse_order_id($noidung);
if($idnhantien > 0){
    echo "ID nhận tiền $idnhantien <br>";
    $total_users = mysqli_num_rows(mysqli_query($ketnoi, "SELECT * FROM `users` WHERE `id` = '".$idnhantien."' ")); 
    if($total_users == '1'){
     $datatkvnd = mysqli_fetch_array(mysqli_query($ketnoi, "SELECT * FROM `users` WHERE `id` = '".$idnhantien."'"));
     $username = $datatkvnd['username'];
   //  echo $username;
     
    $total_records = mysqli_num_rows(mysqli_query($ketnoi, "SELECT * FROM  `server2_autobank` WHERE `tid` = '".$magd."' ")); 
     if($total_records < '1'){

         echo ' chưa tồn tại ID này trên data ';
   mysqli_query($ketnoi, "INSERT INTO `server2_autobank` (`id`, `user_id`, `tid`, `description`, `amount`, `received`, `create_gettime`, `create_time`) VALUES (NULL, '$idnhantien', '$magd', '$noidung', '$tien', '$tien', '$sqlFormattedDateTime', '$time');"); 
// chèn SQL invoices
 mysqli_query($ketnoi, "INSERT INTO `invoices` (`id`, `type`, `user_id`, `trans_id`, `payment_method`, `amount`, `pay`, `status`, `create_date`, `update_date`, `create_time`, `update_time`, `note`, `description`, `tid`, `fake`) VALUES (NULL, 'deposit_money', '$idnhantien', '$magd', 'MBBANK', '$tien', '$tien', '1', '$sqlFormattedDateTime', '$sqlFormattedDateTime', '$time', '$time', '', NULL, NULL, '1');");
 	// cộng tiền 
	mysqli_query($ketnoi, "UPDATE `users` SET `money` = `money` + '".$tien."'  WHERE `id` = '".$idnhantien."'") or exit;
	mysqli_query($ketnoi, "UPDATE `users` SET `total_money` = `total_money` + '".$tien."'  WHERE `id` = '".$idnhantien."'") or exit;
	echo ' đã cộng tiền <br>';
     }

       
       
     }
}else{
  echo 'err<br>';
}
    
    
    
    
    
    
   }
    
    
    
}
    
    
    
    
}







// echo "hello world";