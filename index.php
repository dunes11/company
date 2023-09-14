<?php

use \Razorpay\Api\Api;
use Razorpay\Api\PaymentLink;
use Razorpay\Api\Errors\BadRequestError;

if (isset($_GET["RunFile"])) {
    $company_details2 = Conn()->ExecuteQuery("SELECT `id`, `name`,`logo`, `isTaxable`, `razorpayKeyId`,`razorpayKeySecret`, `paytmMerchantId`, `paytmMerchantKey`, `gst`,`showQr`,`showPaymentLink`,`showBankAccount`,  `address`, `mobile`, `email`, `websiteLink`, `termsAndConditions`, `privacyPolicyLink` FROM `company_dtl` ")->fetch();

    $razorpay_txn =  Conn()->ExecuteQuery("SELECT `id`,`status`,`razorpay_payment_id`,`invoice_id` FROM `razorpay_txn` WHERE `status` = 2")->fetchAll();

    // echo "<pre>";
    // print_r($razorpay_txn);
    // echo "<pre>";
    // exit;

    $api = new Api($company_details2['razorpayKeyId'], $company_details2['razorpayKeySecret']);
    $api_key = $company_details2['razorpayKeyId'];
    $api_secret = $company_details2['razorpayKeySecret'];

    foreach ($razorpay_txn as $key => $value) {

        $payment_id = $value['razorpay_payment_id'] ?? "";
        $api_endpoint = "https://api.razorpay.com/v1/payments/$payment_id";
        $ch = curl_init($api_endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Authorization: Basic " . base64_encode("$api_key:$api_secret")
        ));

        $response = curl_exec($ch);
        curl_close($ch);

        $payment_data = json_decode($response, true);
        // echo "<pre>";
        // print_r($payment_data);
        // echo "<pre>";

        if (isset($payment_data["status"]) == "captured" && $payment_data["order_id"]) {

            $order_id = $payment_data["order_id"];
            $invoice_id = $payment_data['notes']["invoice_id"];

            Conn()->ExecuteQuery("UPDATE `razorpay_txn` SET `status` = '1', `order_id` = '$order_id' WHERE `invoice_id` = '$invoice_id'");

            $product =  Conn()->ExecuteQuery("SELECT `product_id` FROM `invoice_item` WHERE `invoice_id` = '$invoice_id'")->fetch();
            $product_id = $product["product_id"];

            $product_dtl =  Conn()->ExecuteQuery("SELECT `driveFileId` FROM `product` WHERE `id` = '$product_id'")->fetch();
            $DriveFile_id = $product_dtl["driveFileId"];

            $user_dtl =  Conn()->ExecuteQuery("SELECT `u`.`email`
            FROM `user` `u`
            JOIN `razorpay_txn` `r` ON `u`.`id` = `r`.`user_id`
            WHERE `r`.`invoice_id` = '$invoice_id'")->fetch();
            $user_email = $user_dtl["email"];

            $razorpay_txn_status = Conn()->ExecuteQuery("SELECT `status`,`user_id` FROM `razorpay_txn` WHERE `status`=1 AND invoice_id='$invoice_id'")->fetch();
            $user_id = $razorpay_txn_status['user_id'];

            if (isset($razorpay_txn_status["status"]) == 1) {

                $status = $razorpay_txn_status["status"];
                $response =  shareFileByDrive($DriveFile_id, $user_email);
                if (isset($response)) {
                    $response["response"] == 0 ? $status = 0 : $status;
                    $message = $response["message"];
                    Conn()->ExecuteQuery("INSERT into `drive_data_share_report` (user_id,invoice_id,product_id,driveFileId,message,customerEmail,status)
                        values('$user_id','$invoice_id','$product_id','$DriveFile_id','$message','$user_email','$status')");
                    echo "<div>
                        <p>$message</p>
                        <p>Congratulations...</p>
                        </div>";
                }
            }
        } else {
            echo "dont get the response";
        }
    }
} else {
    echo "No perameter given in url =?RunFile";
}
?>
