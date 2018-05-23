<?php
require_once "connect.php";
class Main{

	private $db;
	//channges to the main file

	    function __construct($db) {
        $this->db = $db;
    }


public function fetchOrders($request){

	$USERNAME = $request['USER_NAME'];
	$TABLE = $request['TABLE'];
	$TABLE_ITEMS = $request['TABLE_ITEMS'];
	
	try {	
	
	$sql ="SELECT ORDER_NUMBER,LEDGER_NUMBER,ORDER_DATE,ORDER_AMOUNT,ORDER_DATE,REFERENCE_NUMBER,ORDER_COUNT,DISCOUNT FROM 
	$TABLE WHERE CREATED_BY = '$USERNAME' AND REFERENCE_TYPE = 'MOB' AND ORDER_STATUS='PST' ORDER BY APPROVAL_DATE DESC";
	
	//$sql ="SELECT ORDER_NUMBER,LEDGER_NUMBER,ORDER_DATE,ORDER_AMOUNT,ORDER_DATE,REFERENCE_NUMBER,ORDER_COUNT,DISCOUNT FROM 
	//$TABLE WHERE CREATED_BY = '$USERNAME' AND REFERENCE_TYPE = 'MOB' AND EXPORT_INDICATOR='Q' AND ORDER_STATUS='PST' AND 
	//APPROVED='Y'  AND APPROVAL_DATE BETWEEN CURRENT DATE AND CURRENT DATE+1 DAY ORDER BY ORDER_NUMBER DESC";
	
	
		$final_array = array();
		$dict = array();
		$orders = $this->db->query($sql)->fetchAll();

			foreach ($orders as $order) {
			$dict['order'] = $order;			
			$order_number = $order['ORDER_NUMBER'];
			$reference_number = $order['REFERENCE_NUMBER'];

			$trans = $this->db->query("SELECT ITEM_CODE,DESCRIPTION,ITEM_PRICE,QUANTITY,AMOUNT_VALUE,DISCOUNT FROM STOCK_LEDGER WHERE ORDER_NUMBER = $order_number AND REFERENCE_NUMBER = '$reference_number' UNION 
			SELECT ITEM_CODE,DESCRIPTION,ITEM_PRICE,QUANTITY,AMOUNT_VALUE,DISCOUNT FROM $TABLE_ITEMS WHERE ORDER_NUMBER = 
			$order_number AND REFERENCE_NUMBER = '$reference_number'" )->fetchAll();
				
			$dict['transactions'] = $trans;	

			$final_array[] = $dict;

			}
	

			return json_encode(array('orders'=>$final_array));

		} catch (\Exception $e) {
				return $e->getMessage();
		}finally{
			$this->db = null;
		}	

}
public function fetchItems(){


try {	
	
		$items = $this->db->query("SELECT ITEM_NAME,ITEM_CODE,SALE_PRICE,BUY_PRICE FROM ITEM_MASTER")->fetchAll();
		
		return json_encode(array("items"=>$items));
		} catch (\Exception $e) {
				return $e->getMessage();
		}finally{
			$this->db = null;
		}	
	
	}

public function fetchCustomers($request){

	try {	

		$TABLE = $request['TABLE'];

	
	$sql ="SELECT LEDGER_NUMBER, LEDGER_NAME ,BASE_LOCATION FROM $TABLE";

	$stmt = $this->db->query($sql);
	$item_list = array();

	if($stmt){
		foreach ($stmt as $value) {
			 $item_list[] = $value;

			}
			return json_encode(array("customers"=>$item_list));
		}
	} catch (\Exception $e) {
			return $e->getMessage();
	}finally{
		$this->db = null;
		}	

	}

public function createOrder($request){

			$LEDGER_NUMBER = $request['LEDGER_NUMBER'];
			$USER_NAME = $request['USER_NAME'];
			$TABLE_ORDER = $request['TABLE_ORDER'];
			$TABLE_CUSTOMER = $request['TABLE_CUSTOMER'];

			$stmt = $this->db;			

	try {	
		
				
		$order = $stmt->query("SELECT * FROM $TABLE_ORDER WHERE LEDGER_NUMBER='$LEDGER_NUMBER' 
			AND REFERENCE_TYPE = 'MOB' AND CREATED_BY = '$USER_NAME'")->fetchAll();

		
		$order_dictionary = array();
		$transactions = array();

		foreach ($order as $value) {
			if($value['ORDER_STATUS']=='ACT'){
				$order_dictionary['order'] = $value;
				return json_encode($order_dictionary);
			}	
		}

					$LEDGER_NUMBER = $request['LEDGER_NUMBER'];
					$CUSTOMER_NAME = $request['CUSTOMER_NAME'];
					$TRANSACTION_TYPE = $request['TRANSACTION_TYPE'];
					$LOCATION = $request['LOCATION'];
					$PURCHASE_ORDER = $request['PURCHASE_ORDER'];
					$INVOICE_NUMBER = $request['INVOICE_NUMBER'];					

					$REFERENCE_NUMBER = $request['REFERENCE_NUMBER'];
					$DESCRIPTION = $USER_NAME.":".$LEDGER_NUMBER.time();



					$customer_exists = $stmt->query("SELECT LEDGER_NUMBER FROM $TABLE_CUSTOMER
					 WHERE LEDGER_NUMBER = '$LEDGER_NUMBER'")->fetchColumn();

					if(strlen($customer_exists)>1){

					$fiscal_year = $stmt->query("select fiscal_year from configurations")->fetchColumn();
            		$fiscal_month = $stmt->query("select fiscal_month from configurations")->fetchColumn();

					$sql = "INSERT INTO $TABLE_ORDER (
                    ORDER_TYPE, LEDGER_NUMBER, ORDER_DATE, DESCRIPTION, 
                    ORDER_STATUS, INVOICE_NUMBER,PURCHASE_ORDER, ORDER_LOCATION,
                    FISCAL_YEAR, FISCAL_MONTH, NEXT_STATUS, CREATED_BY,
                    DATE_CREATED, REFERENCE_NUMBER, REFERENCE_TYPE, DATE_MODIFIED,MODIFIED_BY)
                     VALUES (
                    '$TRANSACTION_TYPE','$LEDGER_NUMBER',CURRENT_TIMESTAMP,'$DESCRIPTION','ACT','$INVOICE_NUMBER',
                    '$PURCHASE_ORDER','$LOCATION','$fiscal_year','$fiscal_month','ORD','$USER_NAME',CURRENT_TIMESTAMP,
                    '$REFERENCE_NUMBER', 'MOB', CURRENT_TIMESTAMP, '$USER_NAME')";


                   $stmt->query($sql);

                    $new_order['new_order'] = $stmt->query("SELECT * FROM $TABLE_ORDER WHERE 
                    	REFERENCE_NUMBER='$REFERENCE_NUMBER' AND CREATED_BY = '$USER_NAME'")->fetch() ;

                    return json_encode($new_order);
					
					}else{

						return "customer not found";

					}	
				
				
			} catch (\Exception $e) {
					return $e->getMessage();
			}finally{
				$this->db = null;
			}	
		
		}

public function saveTransactions($request){

	$transactions = array();
	$transactions = json_decode($request['JSON_TRANSACTIONS'],true);
	$order = json_decode($request['JSON_ORDER'],true);
	$payments = json_decode($request['JSON_PAYMENT'],true);
	$TABLE_TRNS = $request['TABLE_TRNS'];
	$TABLE = $request['TABLE'];


	$stmt = $this->db;

	try {	

	$stmt->beginTransaction();

	foreach ($transactions as $transaction) {

		$item_code = $transaction['item_code']; 
		$item_location = $transaction['item_location']; 
		$cust_code = $transaction['cust_code']; 
		$trn_date = $transaction['trn_date']; 
		$ledger_type = $transaction['ledger_type']; 
		$trn_type = $transaction['trn_type']; 
		$order_number = $transaction['order_number']; 
		$order_type = $transaction['order_type']; 
		$item_price = $transaction['item_price'];  
		$quantity = $transaction['quantity'];
		$order_qty = $transaction['order_qty'];
		$amount_value = $transaction['amount_value'];
		$taxable = $transaction['taxable'];
		$tax_code = $transaction['tax_code'];
		$disc_amount = $transaction['disc_amount'];
		$disc_perc = $transaction['disc_perc'];
		$description = $transaction['description'];
		$user = $transaction['user'];
		$serial_no = $transaction['serial_no'];
		$document_number = $transaction['document_number'];
		$document_type = $transaction['document_type'];
		$reference_number = $transaction['reference_number'];
		$time_stamp = "2017-04-28 15:44:16.0";

		
		$fiscal_year = $stmt->query("select fiscal_year from configurations")->fetchColumn();

        $fiscal_month = $stmt->query("select fiscal_month from configurations")->fetchColumn();

        $exists = $stmt->query("select item_code from item_master where item_code='$item_code'")->fetchColumn();
		
        if(!is_null($exists) && strlen($exists)>0){

        	$account_number = "200200";
            $contra_account = "200200";
            $price_type = "RET";
            $user_categ2 = "POS";
            $suom = "";
            $iuom = "";
            $barcode = "";
            $tax_cd = "";
            $tax_amount;
            $value_rate;
            $value_amount;
            $check_rate = 0.0;
            $cost_price = 0.0;
            $total_disc;

            $month = $transaction['month'];
            $day = $transaction['day'];
            $year = $transaction['year'];



            $item = $stmt->query("SELECT * FROM ITEM_MASTER WHERE ITEM_CODE = '$item_code'")->fetch();	

	            $tax_cd = $item['TAX_GROUP'];
        		$check_rate = $item['DISCOUNT_RATE'];
                $cost_price = $item['BUY_PRICE'];
                $barcode = $item['BARCODE'];
                $suom = $item['SUOM'];
                $iuom = $item['IUOM'];

                 if ($tax_cd=="NON") {
                        $tax_code = "E";
                    } else {
                        $tax_code = "A";
	                    
	                }


            $strValueRate = $stmt->query("SELECT REFERENCE_VALUE FROM LIST_CONTROL WHERE MINOR_CODE='$tax_cd' AND  REFERENCE_CODE ='TAX_GROUPS'")->fetchColumn();


            if (strlen($strValueRate)<1) {
                $strValueRate = "0";
            }	

            $value_rate = doubleval($strValueRate)/100;
            $tax_amount = $amount_value-($amount_value/(1+$value_rate));
            $total_disc = $disc_amount * $quantity;
            $value_amount = $amount_value - $tax_amount;


            $sql= "INSERT INTO $TABLE_TRNS (ITEM_CODE, ITEM_LOCATION, LEDGER_NUMBER,LEDGER_TYPE,TRN_TYPE,TRN_DATE,
                    QUANTITY, ITEM_PRICE, AMOUNT_VALUE,TAX_AMOUNT,DISCOUNT,ORD_QUANTITY,
                    DESCRIPTION, ORDER_NUMBER, ORDER_TYPE,SUOM,IUOM,
                    DOCUMENT_NUMBER,DOCUMENT_TYPE,ACCOUNT_NUMBER,CONTRA_ACCOUNT,
                    TRANSFER_LOCATION,RECEIPT_NUMBER,JOURNAL_NUMBER,JOURNAL_TYPE,REFERENCE_NUMBER,
                    FISCAL_YEAR,FISCAL_MONTH,TRN_DAY,TRN_MONTH,TRN_YEAR,
                    CHECK_CATEGORY,BARCODE,TAX_OPTION,PRICE_TYPE,
                    CHARGE_AMOUNT,CHARGE_ACCOUNT,CHARGE_TYPE,TAX_INCLUSIVE,
                    VALUE_TYPE,VALUE_RATE,VALUE_AMOUNT,ITEM_DISCRATE,CHECK_RATE,
                    UNIT_DISCOUNT,ITEM_SERIAL,SUPPLIER_NO,USER_RATE,USER_VALUE3,USER_CATEG2,
                    DATE_CREATED,CREATED_BY, MODIFIED_BY) 
                    VALUES ('$item_code','$item_location','$cust_code','$ledger_type','$trn_type',CURRENT_TIMESTAMP,
                    $quantity,$item_price,$amount_value,$tax_amount,$total_disc,$order_qty,'$description',
                    $order_number,'$order_type','$suom','$iuom',
                   '$document_number','$document_type','$account_number','$contra_account','$item_location',0,0,'DFT',
                   '$reference_number','$fiscal_year','$fiscal_month',$day,$month,$year,'STK','$barcode','$tax_code',
                   '$price_type',0,'*','*','Y','TXR',$value_rate,$value_amount,$disc_perc,$check_rate,$disc_amount,
                   '$serial_no','<>',1.0,'$cost_price','$user_categ2', CURRENT_TIMESTAMP,'$user','$user')";


        	$stmt->query($sql);

        	$sql = "select item_code from stock_ledger where item_code='$item_code' and item_location= '$item_location'";

			$exists=$stmt->query($sql)->fetchColumn();


			$item_code = $transaction['item_code']; 
		$item_location = $transaction['item_location']; 
		$cust_code = $transaction['cust_code']; 
		$trn_date = $transaction['trn_date']; 
		$ledger_type = $transaction['ledger_type']; 
		$trn_type = $transaction['trn_type']; 
		$order_number = $transaction['order_number']; 
		$order_type = $transaction['order_type']; 
		$item_price = $transaction['item_price'];  
		$quantity = $transaction['quantity'];
		$order_qty = $transaction['order_qty'];
		$amount_value = $transaction['amount_value'];
		$taxable = $transaction['taxable'];
		$tax_code = $transaction['tax_code'];
		$disc_amount = $transaction['disc_amount'];
		$disc_perc = $transaction['disc_perc'];
		$description = $transaction['description'];
		$user = $transaction['user'];
		$serial_no = $transaction['serial_no'];
		$document_number = $transaction['document_number'];
		$document_type = $transaction['document_type'];
		$reference_number = $transaction['reference_number'];


        $COLUMN = "" ;
        $final_quantity = $quantity;

        	switch ($trn_type) {
        			case "ADJ":
        			$COLUMN = "ADJUSTMENTS";
        			 	break;
					case "CPO":
        			$COLUMN = "PURCHASED";
        				$final_quantity *= -1;	        			
						break;
					case "SUP":
        			$COLUMN = "PURCHASED";
        				$final_quantity *= -1;
						break;
					case "CSO":
        			$COLUMN = "SOLD";
						break;
					case "CUS":
        			$COLUMN = "SOLD";
						break;
					
        			
        		}


        	if(!is_null($exists) && strlen($exists)>0){

				$update_balances = "UPDATE ITEM_BALANCES 
                    		   SET 	 $COLUMN = $COLUMN + $quantity,
                                     CLOSING = CLOSING - $final_quantity,
                                     VOLUME = VOLUME - $final_quantity,
                                     BUY_PRICE= $item_price,
                                     PRICE_DATE = CURRENT_TIMESTAMP,
                                     AVG_COST = $item_price,
                                     LAST_ACTIVITY = CURRENT_TIMESTAMP, 
                                     DATE_MODIFIED = CURRENT_TIMESTAMP,
                                     MODIFIED_BY = '$user'
                                     WHERE ITEM_CODE='$item_code' AND 
                                     ITEM_LOCATION='$item_location'";	

                                     $stmt->query($update_balances);



        	}else{

        		$insert_into_balances = "INSERT INTO ITEM_BALANCES(ITEM_LOCATION, ITEM_CODE, ADJUSTMENTS, CLOSING, VOLUME, BUY_PRICE, PRICE_DATE, AVG_COST, STOCK_DATE,CATEGORY, FISCAL_YEAR, FISCAL_MONTH, 
                                LAST_ACTIVITY, RUN_CHECK, DATE_CREATED, CREATED_BY, DATE_MODIFIED,
                                MODIFIED_BY,$COLUMN) VALUES(
                                '$item_location', 
                                '$item_code', 
                                $quantity, 
                                $quantity, 
                                $quantity ,
                                $item_price, CURRENT_TIMESTAMP, 
                                $item_price, CURRENT_TIMESTAMP,
                                '*',
                                '$FISCAL_YEAR', 
                                '$FISCAL_MONTH', CURRENT_TIMESTAMP , 
                                'NEW', CURRENT_TIMESTAMP, 
                                '$user', CURRENT_TIMESTAMP ,
                                '$user',$quantity)";

                        $stmt->query($insert_into_balances);
        	}

        }

    }



    if($TABLE_TRNS=='STOCK_LEDGER'){
    		foreach ($payments as $payment) {
			
			$PAY_TYPE = $payment['PAY_TYPE'];
			$AMOUNT = $payment['AMOUNT'];
			$ACCOUNT = $payment['ACCOUNT'];
			$REFERENCE_NUMBER = $payment['REFERENCE_NUMBER'];
			$PAY_DESCRIPTION = $payment['PAY_DESCRIPTION'];
			$PAID_BY = $payment['PAID_BY'];
			$PAY_NUMBER = $payment['PAY_NUMBER'];
			$PAYMENT_STATUS = $payment['PAYMENT_STATUS'];
			$CREATED_BY = $payment['CREATED_BY'];
			$TRN_TYPE = $payment['TRN_TYPE'];
			$ORDER_TYPE = $payment['ORDER_TYPE'];

			$sql = "INSERT INTO PAYMENTS(PAY_TYPE,AMOUNT,ACCOUNT,REFERENCE_NUMBER,PAY_DESCRIPTION,PAY_DATE,PAID_BY, 
                PAY_NUMBER, PAYMENT_STATUS,CREATED_BY,TRN_TYPE,ORDER_TYPE)
                 VALUES('$PAY_TYPE',$AMOUNT,'$ACCOUNT','$REFERENCE_NUMBER','$PAY_DESCRIPTION',CURRENT_TIMESTAMP,
                 '$PAID_BY',$PAY_NUMBER,'$PAYMENT_STATUS','$CREATED_BY','$TRN_TYPE','$ORDER_TYPE')";

                 $stmt->query($sql);
					
			}		

    }


		$ORDER_COUNT = $order['ORDER_COUNT'];
		$ORDER_AMOUNT = $order['ORDER_AMOUNT'];
		$ORDER_QUANTITY = $order['ORDER_QUANTITY'];
		$ACTUAL_AMOUNT = $order['ACTUAL_AMOUNT'];
		$ORDER_STATUS = $order['ORDER_STATUS'];
		$POSTED_BY = $order['POSTED_BY'];
		//$TAX_AMOUNT = $order['TAX_AMOUNT'];
		$DISCOUNT = $order['DISCOUNT'];
		$DELIVERY_METHOD = $order['DELIVERY_METHOD'];
		$AMOUNT = $order['AMOUNT'];
		$BALANCE = $order['BALANCE'];
		$VALUE_AMOUNT1 = $order['VALUE_AMOUNT1'];
		$APPROVED = $order['APPROVED'];
		$APPROVED_BY = $order['APPROVED_BY'];
		$ORDER_NUMBER = $order['ORDER_NUMBER'];
		$NEXT_STATUS = $order['NEXT_STATUS'];

    $sql  = "UPDATE $TABLE SET
    				ORDER_COUNT = $ORDER_COUNT, 
                    ORDER_AMOUNT= $ORDER_AMOUNT, 
                    ORDER_QUANTITY = $ORDER_QUANTITY,
                    ACTUAL_AMOUNT = $ORDER_AMOUNT,
                    ORDER_STATUS='PST',
                    DATE_POSTED = CURRENT_TIMESTAMP,
                    POSTED_BY = '$POSTED_BY',
                    NEXT_STATUS = '$NEXT_STATUS',
                    TAX_AMOUNT= 0,
                    DISCOUNT= $DISCOUNT,
                    DELIVERY_METHOD='$DELIVERY_METHOD',
                    AMOUNT=$AMOUNT,
                    BALANCE=$BALANCE,
                    VALUE_AMOUNT1=$VALUE_AMOUNT1,
                 	APPROVED='$APPROVED', 
                    APPROVED_BY='$APPROVED_BY', 
                    APPROVAL_DATE=CURRENT_TIMESTAMP WHERE ORDER_NUMBER =  '$ORDER_NUMBER'";



	$stmt->query($sql);

    $stmt->commit();

    	return "success";
	
	} catch (\Exception $e) {
			$stmt->rollBack();
			return $e->getMessage();
		}finally{
			$this->db = null;
		}	
	
	}

public function signIn($request){
	$NUMBER = $request['NUMBER'];
	$USERNAME = $request['USERNAME'];
	$PASSWORD = $request['PASSWORD'];
	//if(imei)->if(username)->if(password) 

	try {
		
		$value = $this->db->query("SELECT NUMBER FROM IMEI WHERE NUMBER = '$NUMBER'")->fetchColumn();

		$user_exists = $this->db->query("SELECT FIRST_TIME FROM MOBILE_USERS WHERE USERNAME = '$USERNAME'")->fetchColumn();	

			if(strlen($user_exists)>0){
				

				if($user_exists=='Y'){
						return "create account";
					}elseif ($user_exists=='N') {
						$user_first = $this->db->query("SELECT FIRST_TIME FROM MOBILE_USERS WHERE 
							USERNAME = '$USERNAME' AND PASSWORD = '$PASSWORD'")->fetchColumn();	
						if(strlen($user_first)>0){

							$user_details = $this->db->query("SELECT USERS.USER_LOCATION, MOBILE_USERS.*,MOBILE_USERS.PASSWORD FROM USERS INNER JOIN  MOBILE_USERS ON USERS.USER_NUMBER = MOBILE_USERS.USERNAME WHERE MOBILE_USERS.USERNAME = '$USERNAME'")->fetch();	

							$settings = $this->db->query("SELECT DATA_VALUE FROM CONFIG_SETTINGS 
								WHERE SETTING = 'CHECK_AVAILABLE'")->fetchColumn();	

							$user_details['CHECK_AVAILABLE'] = $settings;	
							$user_payload = array();

							$user_payload['USER_DETAILS'] = $user_details;

							return json_encode($user_payload);

						}else{
							return "incorrect credentials";
						}	
					}
	

			}else{
				return "unregistered user";
			}


	}catch (\Exception $e) {
			return$e->getMessage();
		}finally{
			$this->db =null;
		}

}

public function changePassword($request){

	$USERNAME = $request['USERNAME'];
	$PASSWORD = $request['PASSWORD'];

	try {
		$sql = "UPDATE MOBILE_USERS SET PASSWORD =  '$PASSWORD' WHERE USERNAME='$USERNAME'";
		$stmt = $this->db->query($sql);
		if($stmt){
			return "success";
		}
	} catch (\Exception $e) {
		return $e->getMessage();
	}finally{
		$this->db = null;
	}
	//update password
}

public function signUp($request){

	$USERNAME = $request['USERNAME'];
	$PASSWORD = $request['PASSWORD'];
	$FIRST_TIME = $request['FIRST_TIME'];


	try {
		$sql = "UPDATE MOBILE_USERS SET PASSWORD =  '$PASSWORD' , FIRST_TIME = '$FIRST_TIME' WHERE USERNAME='$USERNAME'";
		$stmt = $this->db->query($sql);
		if($stmt){
			return "success";
		}
	} catch (\Exception $e) {
		return $e->getMessage();
	}finally{
		$this->db = null;
	}
}

public function fetchLocation(){

try {
		$order_location = $this->db->query("SELECT MAIN_LOCATION FROM LOCATIONS")->fetchAll();

		return json_encode(array("order_location"=>$order_location));

	} catch (\Exception $e) {
		return $e->getMessage();
	}finally{
		$this->db = null;
	}

}

public function fetchStockBalances($request){

	$ITEM_CODE = $request['ITEM_CODE'];
	$ITEM_LOCATION = $request['ITEM_LOCATION'];

		try{
			$stock_balance = $this->db->query("SELECT  CLOSING FROM ITEM_BALANCES WHERE ITEM_LOCATION = '$ITEM_LOCATION' AND ITEM_CODE = '$ITEM_CODE'")->fetch();

			return json_encode($stock_balance);	

		}catch(\Exception $e){
			return $e->getMessage()	;
		}finally{
			$this->db = null;
		}

}

public function createJournal($request){

	$stmt = $this->db;
	$stmt->beginTransaction();
		try{

			$JOURNAL_TYPE = $request['JOURNAL_TYPE'];
			$DESCRIPTION = $request['DESCRIPTION'];
			$USER_NUMBER = $request['USER_NUMBER'];
			$LOCATION = $request['LOCATION'];
			$UNIQUE_NO = $request['UNIQUE_NO'];
			$CONTROL_AMOUNT = $request['CONTROL_AMOUNT'];
			$CONTROL_QUANTITY = $request['CONTROL_QUANTITY'];
			$CONTROL_COUNT = $request['CONTROL_COUNT'];
			$JSON_ARRAY = array();
			$JSON_ARRAY = json_decode($request['JSON_ARRAY'],true);

            $FISCAL_YEAR = $this->db->query("SELECT FISCAL_YEAR FROM CONFIGURATIONS")->fetchColumn();
            $FISCAL_MONTH = $this->db->query("SELECT FISCAL_MONTH FROM CONFIGURATIONS")->fetchColumn();
			
			$JOURNAL_NUMBER =$this->db->query("SELECT JOURNAL_NUMBER FROM JOURNAL_CONTROL WHERE JOURNAL_STATUS='ACT' AND CONTROL_COUNT=0")->fetchColumn();

			if(strlen(strval($JOURNAL_NUMBER))<0){

            	$sql = "UPDATE JOURNAL_CONTROL SET
                    DESCRIPTION='$DESCRIPTION', TRN_LOCATION='$LOCATION', JOURNAL_TYPE='$JOURNAL_TYPE',
                    CONTROL_QUANTITY = $CONTROL_QUANTITY,CONTROL_COUNT = $CONTROL_COUNT,CONTROL_AMOUNT = $CONTROL_AMOUNT, DATE_MODIFIED = CURRENT_TIMESTAMP,MODIFIED_BY='$USER_NUMBER' WHERE JOURNAL_NUMBER=$JOURNAL_NUMBER";

					$this->db->query($sql);
			}else{

				$sql = "INSERT INTO JOURNAL_CONTROL(DESCRIPTION, CREATED_BY, DATE_CREATED, CONTROL_QUANTITY,
                    CONTROL_COUNT,CONTROL_AMOUNT, TRN_LOCATION, JOURNAL_DATE, FISCAL_YEAR,
                    FISCAL_MONTH, DATE_MODIFIED, MODIFIED_BY, JOURNAL_TYPE,
                    JOURNAL_STATUS, LEDGER_TYPE, CONTROL_ACCOUNT, EXPORTED_BY,REFERENCE)
                    VALUES('$DESCRIPTION','$USER_NUMBER',CURRENT_TIMESTAMP,'$CONTROL_QUANTITY',$CONTROL_COUNT,
                    $CONTROL_AMOUNT,'$LOCATION',CURRENT_TIMESTAMP,$FISCAL_YEAR,
                    $FISCAL_MONTH,CURRENT_TIMESTAMP,'$USER_NUMBER','$JOURNAL_TYPE','PST','STK','ALL','$UNIQUE_NO','MOB')";

                    $this->db->query($sql);

                    $JOURNAL_NUMBER =$this->db->query("SELECT JOURNAL_NUMBER FROM 
                    	JOURNAL_CONTROL WHERE EXPORTED_BY='$UNIQUE_NO'")->fetchColumn();

				}	

				//$JOURNAL_NUMBER

				

				foreach($JSON_ARRAY as $trns){

				$ITEM_CODE = $trns['ITEM_CODE'];
				$DESCRIPTION = $trns['DESCRIPTION'];
				$QUANTITY = $trns['QUANTITY'];
				$ITEM_PRICE = $trns['ITEM_PRICE'];
				$TRN_TYPE = $trns['TRN_TYPE'];
				$ORDER_TYPE = $trns['ORDER_TYPE'];
				$AMOUNT_VALUE = $trns['AMOUNT_VALUE'];
				$TRN_DAY = $trns['TRN_DAY'];
				$TRN_MONTH = $trns['TRN_MONTH'];
				$TRN_YEAR = $trns['TRN_YEAR'];
				$LEDGER_TYPE = $trns['LEDGER_TYPE'];


				$item = $stmt->query("SELECT * FROM ITEM_MASTER WHERE ITEM_CODE = '$ITEM_CODE'")->fetch();	

				//return json_encode($item);
				$TAX_OPTION = "";
            	
            	$TAX_INCLUSIVE = $item['TAXABLE'];

            	$PRICE_TYPE = "INV";
            	$BARCODE = $item['BARCODE'];

	            $TAX_CD = $item['TAX_GROUP'];/////////
        		
        		$BARCODE = $item['BARCODE'];
                $SUOM = $item['SUOM'];
                $IUOM = $item['IUOM'];

                 if ($TAX_CD=="NON") {
                        $TAX_OPTION = "E";
                    } else {
                        $TAX_OPTION = "A";
	                    }

	              $sql = "INSERT INTO STOCK_TRNS (
                    ITEM_CODE, DESCRIPTION, DATE_CREATED,QUANTITY,
                    ITEM_PRICE,ITEM_LOCATION,JOURNAL_NUMBER,IUOM,
                    ACCOUNT_NUMBER,CREATED_BY,
                    LEDGER_NUMBER,SUOM,CHECK_CATEGORY,BARCODE, 
                    AMOUNT_VALUE, ORD_QUANTITY, ORDER_TYPE, PURCHASE_ORDER,
                    CONTRA_ACCOUNT, JOURNAL_TYPE, FISCAL_YEAR, FISCAL_MONTH,
                    TRN_DAY, TRN_MONTH, TRN_YEAR, TAX_OPTION,
                    PRICE_TYPE, TAX_INCLUSIVE, DATE_MODIFIED, MODIFIED_BY,
                    LEDGER_TYPE, TRN_TYPE,REFERENCE_NUMBER)
                    VALUES('$ITEM_CODE','$DESCRIPTION',CURRENT_TIMESTAMP,$QUANTITY,$ITEM_PRICE,'$LOCATION',
                    '$JOURNAL_NUMBER','$IUOM','*','$USER_NUMBER','ADJUSTMENTS','$SUOM','N/A','$BARCODE','$AMOUNT_VALUE',
                    $QUANTITY,'$ORDER_TYPE','*','*','$JOURNAL_TYPE','$FISCAL_YEAR','$FISCAL_MONTH',$TRN_DAY, $TRN_MONTH, 
                    $TRN_YEAR,'$TAX_OPTION','$PRICE_TYPE','$TAX_INCLUSIVE',CURRENT_TIMESTAMP,'$USER_NUMBER','$LEDGER_TYPE',
                    'ADJ','$UNIQUE_NO')";

                    $stmt->query($sql);


                    $code = $this->db->query("SELECT ITEM_CODE FROM ITEM_BALANCES
                     WHERE ITEM_LOCATION = '$LOCATION' AND ITEM_CODE='$ITEM_CODE'")->fetchColumn();
                                     

                    if(strlen($code)>0){

                    	if ($JOURNAL_TYPE == 'ADJ') {                    			

                    		if($ORDER_TYPE=='OUT'){
                    			$QUANTITY = $QUANTITY*(-1);
                    		}                    



                    		   $update_balances = "UPDATE ITEM_BALANCES 
                    		   SET ADJUSTMENTS=ADJUSTMENTS + $QUANTITY,
                                     CLOSING=CLOSING + $QUANTITY,
                                     VOLUME=VOLUME+$QUANTITY,
                                     BUY_PRICE=$ITEM_PRICE,
                                     PRICE_DATE=CURRENT_TIMESTAMP,
                                     AVG_COST=$ITEM_PRICE,
                                     LAST_ACTIVITY=CURRENT_TIMESTAMP, 
                                     DATE_MODIFIED=CURRENT_TIMESTAMP,
                                     MODIFIED_BY='$USER_NUMBER'
                                     WHERE ITEM_CODE='$ITEM_CODE' AND 
                                     ITEM_LOCATION='$LOCATION'";

                                     $stmt->query($update_balances);

                    	}else{

                    		$update_balances = "UPDATE ITEM_BALANCES 
                    		   SET ADJUSTMENTS = $QUANTITY,
                                     CLOSING = $QUANTITY,
                                     VOLUME = $QUANTITY,
                                     BUY_PRICE= $ITEM_PRICE,
                                     PRICE_DATE = CURRENT_TIMESTAMP,
                                     AVG_COST = $ITEM_PRICE,
                                     LAST_ACTIVITY = CURRENT_TIMESTAMP, 
                                     DATE_MODIFIED = CURRENT_TIMESTAMP,
                                     MODIFIED_BY = '$USER_NUMBER'
                                     WHERE ITEM_CODE='$ITEM_CODE' AND 
                                     ITEM_LOCATION='$LOCATION'";	

                                     $stmt->query($update_balances);

                    	}

                    }else{
                    	$insert_into_balances = "INSERT INTO ITEM_BALANCES(ITEM_LOCATION, ITEM_CODE, ADJUSTMENTS, CLOSING, VOLUME, BUY_PRICE, PRICE_DATE, AVG_COST, STOCK_DATE,CATEGORY, FISCAL_YEAR, FISCAL_MONTH, 
                                LAST_ACTIVITY, RUN_CHECK, DATE_CREATED, CREATED_BY, DATE_MODIFIED,
                                MODIFIED_BY) VALUES(
                                '$LOCATION', 
                                '$ITEM_CODE', 
                                $QUANTITY, 
                                $QUANTITY, 
                                $QUANTITY ,
                                $ITEM_PRICE, CURRENT_TIMESTAMP, 
                                $ITEM_PRICE, CURRENT_TIMESTAMP,
                                '*',
                                '$FISCAL_YEAR', 
                                '$FISCAL_MONTH', CURRENT_TIMESTAMP , 
                                'NEW', CURRENT_TIMESTAMP, 
                                '$USER_NUMBER', CURRENT_TIMESTAMP ,
                                '$USER_NUMBER')";

                        $stmt->query($insert_into_balances);
                    }

				}
				                           
				$stmt->commit();

			return "success";	

		}catch(\Exception $e){
			$stmt->rollBack();
			return $e->getMessage();
		}finally{
			$this->db = null;
		}

}

public function fetchAdjusted($request){

	try{

		$USER_NAME = $request['USER_NAME'];

		$sql ="SELECT JOURNAL_NUMBER,JOURNAL_DATE,CONTROL_AMOUNT,CONTROL_COUNT AS CONTROL_QUANTITY,DESCRIPTION,EXPORTED_BY FROM JOURNAL_CONTROL WHERE CREATED_BY = '$USER_NAME' AND REFERENCE = 'MOB' AND JOURNAL_STATUS = 'PST' AND JOURNAL_TYPE IN('ADJ') ORDER BY JOURNAL_DATE DESC";

		$final_array = array();
		$dict = array();
		$orders = $this->db->query($sql)->fetchAll();
		
			foreach ($orders as $order) {
				$dict['order'] = $order;			
			$reference_number = $order['EXPORTED_BY'];

			$trans = $this->db->query("SELECT ITEM_CODE,DESCRIPTION,ITEM_PRICE,QUANTITY,AMOUNT_VALUE,DISCOUNT FROM STOCK_TRNS WHERE REFERENCE_NUMBER = '$reference_number' UNION 
				SELECT ITEM_CODE,DESCRIPTION,ITEM_PRICE,QUANTITY,AMOUNT_VALUE,DISCOUNT FROM STOCK_LEDGER WHERE REFERENCE_NUMBER = '$reference_number'")->fetchAll();
				
			$dict['transactions'] = $trans;	

			$final_array[] = $dict;

			}

			return json_encode(array('orders'=>$final_array));

	}catch(\Exception $e){
		return $e->getMessage();
	}finally{
		$this->db = null;
	}

}

public function createTransferJournal($request){
	
	$stmt = $this->db; 
	$stmt->beginTransaction();
		try{

			$JOURNAL_TYPE = $request['JOURNAL_TYPE'];
			$DESCRIPTION = $request['DESCRIPTION'];
			$USER_NUMBER = $request['USER_NUMBER'];
			$LOCATION = $request['LOCATION'];
			$UNIQUE_NO = $request['UNIQUE_NO'];
			$CONTROL_AMOUNT = $request['CONTROL_AMOUNT'];
			$CONTROL_QUANTITY = $request['CONTROL_QUANTITY'];
			$CONTROL_COUNT = $request['CONTROL_COUNT'];

			$FROM_LOCATION = $request['FROM_LOCATION'];
			$VIA_LOCATION = $request['VIA_LOCATION'];
			$OTHER_LOCATION = $request['OTHER_LOCATION'];

			$JSON_ARRAY = array();
			$JSON_ARRAY = json_decode($request['JSON_ARRAY'],true);

            $FISCAL_YEAR = $this->db->query("SELECT FISCAL_YEAR FROM CONFIGURATIONS")->fetchColumn();
            $FISCAL_MONTH = $this->db->query("SELECT FISCAL_MONTH FROM CONFIGURATIONS")->fetchColumn();
			
			$JOURNAL_NUMBER =$this->db->query("SELECT JOURNAL_NUMBER FROM JOURNAL_CONTROL WHERE JOURNAL_STATUS='ACT' AND CONTROL_COUNT=0")->fetchColumn();

			if(strlen(strval($JOURNAL_NUMBER))<0){

            	$sql = "UPDATE JOURNAL_CONTROL SET
                    DESCRIPTION='$DESCRIPTION', TRN_LOCATION='$LOCATION', JOURNAL_TYPE='$JOURNAL_TYPE',
                    CONTROL_QUANTITY = $CONTROL_QUANTITY,CONTROL_COUNT = $CONTROL_COUNT,CONTROL_AMOUNT = $CONTROL_AMOUNT, DATE_MODIFIED = CURRENT_TIMESTAMP,MODIFIED_BY='$USER_NUMBER' WHERE JOURNAL_NUMBER=$JOURNAL_NUMBER";

					$this->db->query($sql);

			}else{

				$sql = "INSERT INTO JOURNAL_CONTROL(DESCRIPTION, CREATED_BY, DATE_CREATED, CONTROL_QUANTITY,
                    CONTROL_COUNT,CONTROL_AMOUNT, TRN_LOCATION, JOURNAL_DATE, FISCAL_YEAR,
                    FISCAL_MONTH, DATE_MODIFIED, MODIFIED_BY, JOURNAL_TYPE,
                    JOURNAL_STATUS, LEDGER_TYPE, CONTROL_ACCOUNT, EXPORTED_BY,REFERENCE,FROM_LOCATION,OTHER_LOCATION,VIA_LOCATION)
                    VALUES('$DESCRIPTION','$USER_NUMBER',CURRENT_TIMESTAMP,'$CONTROL_QUANTITY',$CONTROL_COUNT,
                    $CONTROL_AMOUNT,'$LOCATION',CURRENT_TIMESTAMP,$FISCAL_YEAR,
                    $FISCAL_MONTH,CURRENT_TIMESTAMP,'$USER_NUMBER','$JOURNAL_TYPE','PST','STK','ALL','$UNIQUE_NO','MOB',
                    '$FROM_LOCATION','$OTHER_LOCATION','$VIA_LOCATION')";

                    $this->db->query($sql);



                    $JOURNAL_NUMBER =$this->db->query("SELECT JOURNAL_NUMBER FROM 
                    	JOURNAL_CONTROL WHERE EXPORTED_BY='$UNIQUE_NO'")->fetchColumn();
				}	
			
				foreach($JSON_ARRAY as $trns){

				$ITEM_LOCATION = $trns['ITEM_LOCATION'];	
				$ITEM_CODE = $trns['ITEM_CODE'];
				$DESCRIPTION = $trns['DESCRIPTION'];
				$QUANTITY = $trns['QUANTITY'];
				$ITEM_PRICE = $trns['ITEM_PRICE'];
				$TRN_TYPE = $trns['TRN_TYPE'];
				$ORDER_TYPE = "N/A";
				$AMOUNT_VALUE = $trns['AMOUNT_VALUE'];
				$TRN_DAY = $trns['TRN_DAY'];
				$TRN_MONTH = $trns['TRN_MONTH'];
				$TRN_YEAR = $trns['TRN_YEAR'];
				$LEDGER_TYPE = $trns['LEDGER_TYPE'];
				
				$SUPPLIER_NO = $trns['SUPPLIER_NO'];
				$TRANSFER_LOCATION = $trns['TRANSFER_LOCATION'];


				$item = $stmt->query("SELECT * FROM ITEM_MASTER WHERE ITEM_CODE = '$ITEM_CODE'")->fetch();	

				//return json_encode($item);
				$TAX_OPTION = "";
            	
            	$TAX_INCLUSIVE = $item['TAXABLE'];

            	$PRICE_TYPE = "INV";
            	$BARCODE = $item['BARCODE'];

	            $TAX_CD = $item['TAX_GROUP'];/////////
        		
        		$BARCODE = $item['BARCODE'];
                $SUOM = $item['SUOM'];
                $IUOM = $item['IUOM'];

                 if ($TAX_CD=="NON") {
                        $TAX_OPTION = "E";
                    } else {
                        $TAX_OPTION = "A";
	                    }


	              $FROM = $ITEM_LOCATION;
                  $TO =	($SUPPLIER_NO=='N/A'?$TRANSFER_LOCATION:$SUPPLIER_NO);            

	              $sql = "INSERT INTO STOCK_TRNS (
                    ITEM_CODE, DESCRIPTION, DATE_CREATED,QUANTITY,
                    ITEM_PRICE,ITEM_LOCATION,JOURNAL_NUMBER,IUOM,
                    ACCOUNT_NUMBER,CREATED_BY,
                    LEDGER_NUMBER,SUOM,CHECK_CATEGORY,BARCODE, 
                    AMOUNT_VALUE, ORD_QUANTITY, ORDER_TYPE, PURCHASE_ORDER,
                    CONTRA_ACCOUNT, JOURNAL_TYPE, FISCAL_YEAR, FISCAL_MONTH,
                    TRN_DAY, TRN_MONTH, TRN_YEAR, TAX_OPTION,
                    PRICE_TYPE, TAX_INCLUSIVE, DATE_MODIFIED, MODIFIED_BY,
                    LEDGER_TYPE, TRN_TYPE,REFERENCE_NUMBER,TRANSFER_LOCATION,SUPPLIER_NO)
                    VALUES('$ITEM_CODE','$DESCRIPTION',CURRENT_TIMESTAMP,$QUANTITY,$ITEM_PRICE,'$FROM',
                    '$JOURNAL_NUMBER','$IUOM','*','$USER_NUMBER','TRANFERS','$SUOM','N/A','$BARCODE','$AMOUNT_VALUE',
                    $QUANTITY,'$ORDER_TYPE','*','*','$JOURNAL_TYPE','$FISCAL_YEAR','$FISCAL_MONTH',$TRN_DAY, $TRN_MONTH, 
                    $TRN_YEAR,'$TAX_OPTION','$PRICE_TYPE','$TAX_INCLUSIVE',CURRENT_TIMESTAMP,'$USER_NUMBER','$LEDGER_TYPE',
                   	'$JOURNAL_TYPE','$UNIQUE_NO','$TO','$SUPPLIER_NO')";

                    $stmt->query($sql);

                    $sql_rec = "INSERT INTO STOCK_TRNS (
                    ITEM_CODE, DESCRIPTION, DATE_CREATED,QUANTITY,
                    ITEM_PRICE,ITEM_LOCATION,JOURNAL_NUMBER,IUOM,
                    ACCOUNT_NUMBER,CREATED_BY,
                    LEDGER_NUMBER,SUOM,CHECK_CATEGORY,BARCODE, 
                    AMOUNT_VALUE, ORD_QUANTITY, ORDER_TYPE, PURCHASE_ORDER,
                    CONTRA_ACCOUNT, JOURNAL_TYPE, FISCAL_YEAR, FISCAL_MONTH,
                    TRN_DAY, TRN_MONTH, TRN_YEAR, TAX_OPTION,
                    PRICE_TYPE, TAX_INCLUSIVE, DATE_MODIFIED, MODIFIED_BY,
                    LEDGER_TYPE, TRN_TYPE,REFERENCE_NUMBER,TRANSFER_LOCATION,SUPPLIER_NO)
                    VALUES('$ITEM_CODE','$DESCRIPTION',CURRENT_TIMESTAMP,$QUANTITY,$ITEM_PRICE,'$FROM',
                    '$JOURNAL_NUMBER','$IUOM','*','$USER_NUMBER','TRANFERS','$SUOM','N/A','$BARCODE','$AMOUNT_VALUE',
                    $QUANTITY,'$ORDER_TYPE','*','*','$JOURNAL_TYPE','$FISCAL_YEAR','$FISCAL_MONTH',$TRN_DAY, $TRN_MONTH, 
                    $TRN_YEAR,'$TAX_OPTION','$PRICE_TYPE','$TAX_INCLUSIVE',CURRENT_TIMESTAMP,'$USER_NUMBER','$LEDGER_TYPE',
                    'RCV','$UNIQUE_NO','$TO','$SUPPLIER_NO')";

					$stmt->query($sql_rec);

                    $code = $this->db->query("SELECT ITEM_CODE FROM ITEM_BALANCES
                     WHERE ITEM_LOCATION = '$TO' AND ITEM_CODE='$ITEM_CODE'")->fetchColumn();
                                


                    if(strlen($code)>0){                    			

                    		$update_balances = "UPDATE ITEM_BALANCES 
                    		   SET TRANSFERRED=TRANSFERRED + $QUANTITY,
                                     CLOSING=CLOSING - $QUANTITY,
                                     VOLUME=VOLUME-$QUANTITY,
                                     BUY_PRICE=$ITEM_PRICE,
                                     PRICE_DATE=CURRENT_TIMESTAMP,
                                     AVG_COST=$ITEM_PRICE,
                                     LAST_ACTIVITY=CURRENT_TIMESTAMP, 
                                     DATE_MODIFIED=CURRENT_TIMESTAMP,
                                     MODIFIED_BY='$USER_NUMBER'
                                     WHERE ITEM_CODE='$ITEM_CODE' AND 
                                     ITEM_LOCATION='$FROM'";

                                     $stmt->query($update_balances);

                    		$update_balances_1 = "UPDATE ITEM_BALANCES 
                    		   SET RECEIVED=RECEIVED + $QUANTITY,
                                     CLOSING=CLOSING + $QUANTITY,
                                     VOLUME=VOLUME+$QUANTITY,
                                     BUY_PRICE=$ITEM_PRICE,
                                     PRICE_DATE=CURRENT_TIMESTAMP,
                                     AVG_COST=$ITEM_PRICE,
                                     LAST_ACTIVITY=CURRENT_TIMESTAMP, 
                                     DATE_MODIFIED=CURRENT_TIMESTAMP,
                                     MODIFIED_BY='$USER_NUMBER'
                                     WHERE ITEM_CODE='$ITEM_CODE' AND 
                                     ITEM_LOCATION='$TO'";

                                     $stmt->query($update_balances_1);


                    			
                    }else{

                    		$update_balances = "UPDATE ITEM_BALANCES
                    		   SET TRANSFERRED=TRANSFERRED + $QUANTITY,
                                     CLOSING=CLOSING - $QUANTITY,
                                     VOLUME=VOLUME-$QUANTITY,
                                     BUY_PRICE=$ITEM_PRICE,
                                     PRICE_DATE=CURRENT_TIMESTAMP,
                                     AVG_COST=$ITEM_PRICE,
                                     LAST_ACTIVITY=CURRENT_TIMESTAMP, 
                                     DATE_MODIFIED=CURRENT_TIMESTAMP,
                                     MODIFIED_BY='$USER_NUMBER'
                                     WHERE ITEM_CODE='$ITEM_CODE' AND 
                                     ITEM_LOCATION='$FROM'";

                                     $stmt->query($update_balances);

                    	$insert_into_balances = "INSERT INTO ITEM_BALANCES(ITEM_LOCATION, ITEM_CODE, RECEIVED, CLOSING, VOLUME, BUY_PRICE, PRICE_DATE, AVG_COST, STOCK_DATE,CATEGORY, FISCAL_YEAR, FISCAL_MONTH, 
                                LAST_ACTIVITY, RUN_CHECK, DATE_CREATED, CREATED_BY, DATE_MODIFIED,
                                MODIFIED_BY) 
                                VALUES('$TO', 
                                '$ITEM_CODE', 
                                $QUANTITY, 
                                $QUANTITY, 
                                $QUANTITY ,
                                $ITEM_PRICE, CURRENT_TIMESTAMP, 
                                $ITEM_PRICE, CURRENT_TIMESTAMP,
                                '*',
                                '$FISCAL_YEAR', 
                                '$FISCAL_MONTH', CURRENT_TIMESTAMP , 
                                'NEW', CURRENT_TIMESTAMP, 
                                '$USER_NUMBER', CURRENT_TIMESTAMP ,
                                '$USER_NUMBER')";

                     			$stmt->query($insert_into_balances);
                    }

				}
				                           
				$stmt->commit();

				return "success";	

		}catch(\Exception $e){
			$stmt->rollBack();
			return $e->getMessage();
		}finally{
			$this->db = null;
		}

}

public function fetchTransfered($request){

	try{

		$USER_NAME = $request['USER_NAME'];

		$sql ="SELECT JOURNAL_NUMBER,JOURNAL_DATE,CONTROL_AMOUNT,CONTROL_COUNT AS CONTROL_QUANTITY,DESCRIPTION,EXPORTED_BY,OTHER_LOCATION,TRN_LOCATION,FROM_LOCATION FROM JOURNAL_CONTROL WHERE CREATED_BY = '$USER_NAME' AND REFERENCE = 'MOB' AND JOURNAL_STATUS = 'PST' AND JOURNAL_TYPE IN('TSF','ITF') ORDER BY JOURNAL_DATE DESC";

		/*$sql ="SELECT JOURNAL_NUMBER,JOURNAL_DATE,CONTROL_AMOUNT,CONTROL_COUNT AS CONTROL_QUANTITY,DESCRIPTION,EXPORTED_BY FROM JOURNAL_CONTROL WHERE CREATED_BY = '$USER_NAME' AND REFERENCE = 'MOB' AND JOURNAL_STATUS = 'PST' AND JOURNAL_TYPE IN('TSF','ITF') ORDER BY JOURNAL_DATE DESC";*/

		$final_array = array();
		$dict = array();
		$orders = $this->db->query($sql)->fetchAll();
		
			foreach ($orders as $order) {
				$dict['order'] = $order;			
			$reference_number = $order['EXPORTED_BY'];

			$trans = $this->db->query("SELECT ITEM_CODE,DESCRIPTION,ITEM_PRICE,QUANTITY,AMOUNT_VALUE,DISCOUNT FROM STOCK_TRNS WHERE REFERENCE_NUMBER = '$reference_number' UNION 
				SELECT ITEM_CODE,DESCRIPTION,ITEM_PRICE,QUANTITY,AMOUNT_VALUE,DISCOUNT FROM STOCK_LEDGER WHERE REFERENCE_NUMBER = '$reference_number'")->fetchAll();
				
			$dict['transactions'] = $trans;	

			$final_array[] = $dict;

			}

			return json_encode(array('orders'=>$final_array));

	}catch(\Exception $e){
		return $e->getMessage();
	}finally{
		$this->db = null;
	}

}


public function fetchDeliveries($request){

	$TABLE = $request['TABLE'];
	$TABLE_ITEMS = $request['TABLE_ITEMS'];
	$LEDGER_NUMBER = $request['LEDGER_NUMBER'];
	
	try {	
	
	$sql ="SELECT ORDER_NUMBER,LEDGER_NUMBER,ORDER_DATE,ORDER_AMOUNT,ORDER_DATE,REFERENCE_NUMBER,ORDER_COUNT,DISCOUNT FROM 
	$TABLE WHERE REFERENCE_TYPE = 'MOB' AND ORDER_STATUS='PST' AND DELIVERY_COMPLETE ='N' AND LEDGER_NUMBER = 
	'$LEDGER_NUMBER' ORDER BY APPROVAL_DATE DESC";

	//return $sql;

	//$sql ="SELECT ORDER_NUMBER,LEDGER_NUMBER,ORDER_DATE,ORDER_AMOUNT,ORDER_DATE,REFERENCE_NUMBER,ORDER_COUNT,DISCOUNT FROM 
	//$TABLE WHERE CREATED_BY = '$USERNAME' AND REFERENCE_TYPE = 'MOB' AND EXPORT_INDICATOR='Q' AND ORDER_STATUS='PST' AND 
	//APPROVED='Y'  AND APPROVAL_DATE BETWEEN CURRENT DATE AND CURRENT DATE+1 DAY ORDER BY ORDER_NUMBER DESC";
	
		$final_array = array();
		$dict = array();
		$orders = $this->db->query($sql)->fetchAll();

			foreach ($orders as $order) {
			$dict['order'] = $order;			
			$order_number = $order['ORDER_NUMBER'];
			$reference_number = $order['REFERENCE_NUMBER'];

			$trans = $this->db->query("SELECT ITEM_CODE,DESCRIPTION,ITEM_PRICE,QUANTITY,AMOUNT_VALUE,DISCOUNT,DELIVERED_QTY,
				ITEM_LOCATION,ORDER_NUMBER,ORDER_TYPE FROM STOCK_LEDGER WHERE ORDER_NUMBER = $order_number AND REFERENCE_NUMBER = '$reference_number'  AND ORD_QUANTITY <> DELIVERED_QTY UNION SELECT ITEM_CODE,DESCRIPTION,ITEM_PRICE,QUANTITY,AMOUNT_VALUE,DISCOUNT,DELIVERED_QTY,
				ITEM_LOCATION,ORDER_NUMBER,ORDER_TYPE FROM $TABLE_ITEMS WHERE ORDER_NUMBER = $order_number AND REFERENCE_NUMBER = '$reference_number' AND ORD_QUANTITY <> DELIVERED_QTY")->fetchAll();

			$dict['transactions'] = $trans;	

			$final_array[] = $dict;

			}

			return json_encode(array('orders'=>$final_array));

		} catch (\Exception $e) {
				return $e->getMessage();
		}finally{
			$this->db = null;
		}	

}

public function createDeliveryNumber($request){

			$LEDGER_NUMBER = $request['LEDGER_NUMBER'];
			$USER_NAME = $request['USER_NAME'];
			$TABLE_ORDER = $request['TABLE_ORDER'];
			$TABLE_CUSTOMER = $request['TABLE_CUSTOMER'];

			$stmt = $this->db;			


	try {	
		
		$order = $stmt->query("SELECT * FROM $TABLE_ORDER WHERE LEDGER_NUMBER='$LEDGER_NUMBER' 
			AND DELIVERY_COMPLETE = 'N'")->fetchAll();

		$order_dictionary = array();
		$transactions = array();

		foreach ($order as $value) {
			if($value['ORDER_STATUS']=='ACT'){
				$order_dictionary['order'] = $value;
				return json_encode($order_dictionary);
			}	
		}

					$LEDGER_NUMBER = $request['LEDGER_NUMBER'];
					$CUSTOMER_NAME = $request['CUSTOMER_NAME'];
					$TRANSACTION_TYPE = $request['TRANSACTION_TYPE'];
					$LOCATION = $request['LOCATION'];
					//$PURCHASE_ORDER = $request['PURCHASE_ORDER'];
					//$INVOICE_NUMBER = $request['INVOICE_NUMBER'];					
					$REFERENCE_NUMBER = $request['REFERENCE_NUMBER'];
					$DESCRIPTION = $USER_NAME.":".$LEDGER_NUMBER.time();

					$customer_exists = $stmt->query("SELECT LEDGER_NUMBER FROM $TABLE_CUSTOMER
					 WHERE LEDGER_NUMBER = '$LEDGER_NUMBER'")->fetchColumn();

					$assoc_number = $stmt->query("SELECT ORDER_NUMBER FROM $TABLE_CUSTOMER
					 WHERE LEDGER_NUMBER = '$LEDGER_NUMBER'")->fetchColumn();

					if(strlen($customer_exists)>1){

					$fiscal_year = $stmt->query("select fiscal_year from configurations")->fetchColumn();
            		$fiscal_month = $stmt->query("select fiscal_month from configurations")->fetchColumn();

					$sql = "INSERT INTO $TABLE_ORDER (
                    ORDER_TYPE, LEDGER_NUMBER, ORDER_DATE, DESCRIPTION, 
                    ORDER_STATUS, ORDER_LOCATION,
                    FISCAL_YEAR, FISCAL_MONTH, NEXT_STATUS, CREATED_BY,
                    DATE_CREATED, REFERENCE_NUMBER, REFERENCE_TYPE, DATE_MODIFIED,MODIFIED_BY,ASSOC_TYPE,ASSOC_NUMBER)
                     VALUES (
                    '$TRANSACTION_TYPE','$LEDGER_NUMBER',CURRENT_TIMESTAMP,'$DESCRIPTION','ACT',
                    '$LOCATION','$fiscal_year','$fiscal_month','ORD','$USER_NAME',CURRENT_TIMESTAMP,
                    '$REFERENCE_NUMBER', 'MOB', CURRENT_TIMESTAMP, '$USER_NAME','SOR',$assoc_number)";


                   $stmt->query($sql);

                    $new_order['new_order'] = $stmt->query("SELECT * FROM $TABLE_ORDER WHERE 
                    	REFERENCE_NUMBER='$REFERENCE_NUMBER' AND CREATED_BY = '$USER_NAME'")->fetch() ;

                    return json_encode($new_order);
					
					}else{

						return "customer not found";

					}	
				
				
			} catch (\Exception $e) {
					return $e->getMessage();
			}finally{
				$this->db = null;
			}	
		
		}

public function postDelivery($request)	{


			//$TABLE_ORDER = $request['TABLE_ORDER'];
			$TABLE_MASTER = $request['TABLE_MASTER'];
			$TABLE_ITEMS = $request['TABLE_ITEMS'];

			$DELIVERY_NUMBER = $request['DELIVERY_NUMBER'];
			$ORDER_NUMBER = $request['ORDER_NUMBER'];
			$ORDER_TYPE = $request['ORDER_TYPE'];
			$CREATED_BY = $request['CREATED_BY'];
			$MODIFIED_BY = $request['MODIFIED_BY'];	
								
			$AMOUNT_VALUE = 0;
			$TAX_AMOUNT = 0;					


			$transactions = array();
			$transactions = json_decode($request['TRANSACTIONS'],true);


			$this->db->beginTransaction();

			$sql = "";


			try{

				$order_count =0;
				$complete_count = 0;
				//order_amount = 0;

				foreach ($transactions as $transaction) {

				$ITEM_CODE =$transaction['item_code'];
				$DESCRIPTION =$transaction['description'];
				$ITEM_LOCATION =$transaction['item_location'];
				$ORDERED_QUANTITY =$transaction['quantity'];
				$DELIVERED_QUANTITY =$transaction['posted_qty'];

					
				if(doubleval($DELIVERED_QUANTITY)>0){

					$order_count++;

					$sql="INSERT INTO DELIVERY 
					(DELIVERY_NUMBER,ORDER_NUMBER,ORDER_TYPE,ITEM_CODE,DESCRIPTION,ITEM_LOCATION,ORDERED_QUANTITY,
					DELIVERED_QUANTITY,CREATED_BY,MODIFIED_BY,DATE_CREATED,DATE_MODIFIED) VALUES ('$DELIVERY_NUMBER','$ORDER_NUMBER','$ORDER_TYPE',
					'$ITEM_CODE','$DESCRIPTION','$ITEM_LOCATION',$ORDERED_QUANTITY,$DELIVERED_QUANTITY,'$CREATED_BY',
					'$MODIFIED_BY',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)";

					$this->db->query($sql);			
				}	
				
					$complete_count += ($ORDERED_QUANTITY - $DELIVERED_QUANTITY);

				$sql = "SELECT  AMOUNT_VALUE, TAX_AMOUNT FROM $TABLE_ITEMS WHERE ORDER_NUMBER = 
				'$ORDER_NUMBER' AND ITEM_CODE = '$ITEM_CODE'";
				$item_details = $this->db->query($sql)->fetch();	

				$AMOUNT_VALUE += doubleval($item_details['AMOUNT_VALUE']);
				$TAX_AMOUNT += doubleval($item_details['TAX_AMOUNT']);

				$sql = "UPDATE $TABLE_ITEMS SET DELIVERED_QTY = $DELIVERED_QUANTITY + DELIVERED_QTY WHERE ORDER_NUMBER = 
				'$ORDER_NUMBER' AND ITEM_CODE = '$ITEM_CODE'";
				$this->db->query($sql);
				}	

				if($complete_count == 0){
				$sql = "UPDATE DELIVERY_ORDERS SET DELIVERY_COMPLETE = 'Y', APPROVED_BY = '$CREATED_BY',APPROVAL_DATE = CURRENT_TIMESTAMP,TAX_AMOUNT = $TAX_AMOUNT,AMOUNT_VALUE = $AMOUNT_VALUE WHERE DELIVERY_NUMBER = $DELIVERY_NUMBER";
				$this->db->query($sql);
				} 

				$sql = "UPDATE DELIVERY_ORDERS SET ORDER_COUNT = $order_count,ORDER_AMOUNT =  WHERE DELIVERY_NUMBER = 
				$DELIVERY_NUMBER TAX_AMOUNT = $TAX_AMOUNT,AMOUNT_VALUE = $AMOUNT_VALUE WHERE DELIVERY_NUMBER = $DELIVERY_NUMBER";

				$this->db->query($sql);

				if($this->db->commit()){
					return "success";
				}else{
					$this->db->rollBack();
				}

			}catch(\Exception $e){
				$this->db->rollBack();	
			}finally{
				$this->db =null;
			}


	}

public function locationQuantity($request){
			$ITEM_CODE = $request['ITEM_CODE'];
			try{
				$sql ="SELECT  CLOSING,ITEM_CODE,ITEM_LOCATION FROM ITEM_BALANCES WHERE ITEM_LOCATION<>'' AND ITEM_CODE = '$ITEM_CODE'";
				$stock_balance = $this->db->query($sql)->fetchAll();
				return json_encode($stock_balance);

			}catch(\Exception $e){
				return $e->getMessage();
			}finally{
				$this->db =null;
			}

	}

}

