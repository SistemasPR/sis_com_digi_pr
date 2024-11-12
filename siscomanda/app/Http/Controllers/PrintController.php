<?php

namespace App\Http\Controllers;

use Mike42\Escpos\Printer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

class PrintController extends Controller
{
    //PRUEBA
    public function index(Request $request) {
        
        self::ticketBoletadeVenta($request->order,$request->items,$request->store,$request->correlativo,$request->printer);
        self::ticketCocina($request->order,$request->items,$request->printer);
        return response()->json(["message" => "HOLAAAAAAA"], 200);
    }

    public function ticketBoletadeVentaApi(Request $request) {
    
        $order = (object) $request->order;
        self::ticketBoletadeVenta($request->order,$request->items,$request->store,$request->correlativo,$request->printer);
        $arApp = ["ANDROID",'IOS','WEB','CALL'];
        $source_app = strtoupper($order->source_app);
        if(in_array($source_app,$arApp)){
            //self::ticketBoletadeVenta($request->order,$request->items,$request->store,$request->correlativo,$request->printer);
            self::ticketDeliveryDriver($request->order,$request->items,$request->store,$request->printer);
        }
    }


    public function ticketVentaSalon(Request $request) {
        
        self::ticketBoletadeVenta($request->order,$request->items,$request->store,$request->correlativo,$request->printer);
        self::ticketCocina($request->order,$request->items,$request->printer);
    }
    public function ticketComandaApi(Request $request) {
        self::ticketCocina($request->order,$request->items,$request->printer);
    }

    public function ticketCierreApi(Request $request) {
        //info(json_encode($request->all()));
        self::ticketCierreCaja($request->store,$request->apertura_s,$request->suma_S,$request->ventas,$request->transactions_S,$request->usuario,$request->store_balance,$request->mercaderia,$request->printer);
    }

    public function ticketPaloteoApi(Request $request) {
        //info(json_encode($request->all()));
        self::ticketPaloteo($request->store,$request->data,$request->printer);
    }

    public function ticketInventarioApi(Request $request) {
        //info(json_encode($request->all()));
        self::ticketInventario($request->store,$request->data,$request->printer);
    }

    public function ticketMovimientoApi(Request $request) {
        self::ticketMovimiento($request->movimiento,$request->store,$request->printer);
    }



    public function testingPrinterConnection(Request $request) {
        try {
            //code...
            $nombreImpresora = "$request->name";
            $connector = new WindowsPrintConnector($nombreImpresora);
            $impresora = new Printer($connector);
            $impresora->text("<<<<<<<<<<<<<<<<<<<<<<<<<<>>>>>>>>>>>>>>>>>>>>>>>>>>>>\n");   
            $impresora->text("LA CONEXIÓN SE REALIZO CON EXITO\n");   
            $impresora->text("<<<<<<<<<<<<<<<<<<<<<<<<<<>>>>>>>>>>>>>>>>>>>>>>>>>>>>\n");   
            $impresora->feed(5);
            $impresora->cut();
            $impresora->close();
            return response()->json(["message" => "CONEXIÓN EXITOSA"], 200 );
        } catch (\Throwable $th) {
            //throw $th;
            // Capturar mensaje del error
            $errorMessage = $th->getMessage();
            // Capturar el archivo donde ocurrió el error
            $errorFile = $th->getFile();
            // Capturar la línea donde ocurrió el error
            $errorLine = $th->getLine();
            $data = [
                "message" => "Error: {$errorMessage} en el archivo {$errorFile} en la línea {$errorLine}"
            ];
            Log::channel('stderr')->info("Error: {$errorMessage} en el archivo {$errorFile} en la línea {$errorLine}");
            return  $data;
        }
    }

    public static function ticketBoletadeVenta($order,$items,$store,$correlativo,$printer){
        
        $nombreImpresora = "$printer";
        $order = (object) $order;
        $items = (object) $items;
        $store = (object) $store;
        //$connector = new WindowsPrintConnector($nombreImpresora);
        try {
            $connector = new WindowsPrintConnector($nombreImpresora);
            $impresora = new Printer($connector);
            $date = date('d-m-Y');
            $horaActual = date('h:i:s A');
            $title_impresion = $order->fiscal_doc_type == "DNI" ? "BOLETA DE VENTA ELECTRÓNICA" : "FACTURA ELECTRÓNICA";
            $impresora->setFont(PRINTER::FONT_B);
            $impresora->setJustification(Printer::JUSTIFY_CENTER);
            $impresora->setTextSize(1, 1);
            $impresora->setEmphasis(true);
            $impresora->text("PASTAS Y PIZZAS\n");
            $impresora->text("$store->razon_social\n");   
            $impresora->text("$store->nro_ruc\n");   
            $impresora->text("$store->street_name  $store->street_number\n");   
            $impresora->text("$store->district_old, LIMA - LIMA\n");   
            $impresora->text("(01) 207 - 8130\n");   
            $impresora->setFont(PRINTER::FONT_B);
            $impresora->text("www.pizzaraul.com\n");   
            $impresora->setEmphasis(true);
            $impresora->text("================================================================\n");
            $impresora->setFont(PRINTER::FONT_B);
            //contenido source
            $phone = $order->user_phone == null || $order->user_phone == "999999999" ? '' : $order->user_phone;
            $impresora->text("$title_impresion\n");
            $impresora->text("$correlativo\n");
            $impresora->setTextSize(1, 1);
            $impresora->text("================================================================\n");
            $impresora->setJustification(Printer::JUSTIFY_LEFT);
            $impresora->text("F.Emisión: $date\n");
            $impresora->text("H.Emisión: $horaActual\n");
            $impresora->text("Orden de compra: $order->id\n");
            $impresora->text("Cliente: $order->fiscal_name\n");
            $impresora->text("Telefono: $phone\n");
            $impresora->text("$order->fiscal_doc_type: $order->fiscal_doc_number\n");
            $impresora->text("Dirección: $order->street_name $order->street_number \n");
            //$impresora->text("Referencia: $order->reference \n");
            $impresora->text("================================================================\n");
    
            $impresora->text("i     Descripción                                             s/\n");
            $impresora->text("----------------------------------------------------------------\n");
    
            $index = 0;
            $auxItem = 0;
            $auxPromId = 0;
    
            foreach ($items as $item) {
                $item = (object) $item;
                $impresora->setFont(PRINTER::FONT_B);
                if(($item->item_id != $auxItem ) && ($item->promotion_id != null) ){
                    $index ++;
                    $auxItem = $item->item_id;
                    $auxPromId = $item->promotion_id;
                    $nombre = mb_substr($item->promotion_name, 0, 50);
                    $precio = number_format($item->price, 2, '.', ''); 
                    $qprom = $index." > ".$item->q_prom."x ";
                    $order_value = $precio * $item->q_prom;
                    // Divide la línea en tres partes
                    $parteIzquierda = $qprom . $nombre;
                    $parteCentro = "";
                    $parteDerecha = $order_value;
                
                    // Calcula la cantidad de espacios entre las partes
                    $espaciosCentro = self::CalculaEspacio($parteIzquierda,$parteDerecha);
                    
                    // Alineación a la izquierda
                    $impresora->text($parteIzquierda);
                    
                    // Alineación central (agrega espacios en blanco)
                    for ($i = 0; $i < $espaciosCentro; $i++) {
                        $parteCentro .= " ";
                    }
                    $impresora->text($parteCentro);
                    
                    // Alineación a la derecha
                    $impresora->text($parteDerecha);
                    $impresora->text("\n");

                    $q_promo = $item->q_prom;
                    
                    $terms = $item->product_terms != null ? ' ('.$item->product_terms.')' : '';
                    if($item->size_id == 9) {
                        $arApp = ["ANDROID",'IOS','WEB'];
                        $source_app = strtoupper($order->source_app);
                        $q_total = 0;
                        if(in_array($source_app,$arApp) && $q_promo != 0){
                            $q_total = $item->quantity;
                        }else{
                            $q_total = $item->quantity;
                        }

                        $nombre_it = $q_total.'x'.' '.$item->product_name.' '.$terms;
                    }else{
                        $arApp = ["ANDROID",'IOS','WEB'];
                        $source_app = strtoupper($order->source_app);
                        $q_total = 0;
                        if(in_array($source_app,$arApp) && $q_promo != 0){
                            $q_total = $item->quantity;
                        }else{
                            $q_total = $item->quantity;
                        }
                        $arSizeName = explode('(',$item->size_name);
                        $nombre_it = $q_total.'x'.' '.$item->product_name.' '.$terms.' '.$arSizeName[0];
                    }

                    //$nombre_it = $item->quantity.'x'.' '.$item->product_name.' '.$item->size_name;
                    $impresora->setFont(PRINTER::FONT_B);
                    $impresora->text("  >> $nombre_it \n");

                }elseif($item->promotion_id == $auxPromId && $item->promotion_id != null){
                    $terms = $item->product_terms != null ? ' ('.$item->product_terms.')' : '';
                    $q_promo = $item->q_prom;
                    if($item->size_id == 9) {
                        $arApp = ["ANDROID",'IOS','WEB'];
                        $source_app = strtoupper($order->source_app);
                        $q_total = 0;
                        if(in_array($source_app,$arApp) && $q_promo != 0){
                            $q_total = $item->quantity;
                        }else{
                            $q_total = $item->quantity;
                        }

                        $nombre_it = $q_total.'x'.' '.$item->product_name.' '.$terms;
                    }else{
                        $arApp = ["ANDROID",'IOS','WEB'];
                        $source_app = strtoupper($order->source_app);
                        $q_total = 0;
                        if(in_array($source_app,$arApp) && $q_promo != 0){
                            $q_total = $item->quantity;
                        }else{
                            $q_total = $item->quantity;
                        }
                        $arSizeName = explode('(',$item->size_name);
                        $nombre_it = $q_total.'x'.' '.$item->product_name.' '.$terms.' '.$arSizeName[0];
                    }

                    //$nombre_it = $item->quantity.'x'.' '.$item->product_name.' '.$item->size_name;
                    $impresora->setFont(PRINTER::FONT_B);
                    $impresora->text("  >> $nombre_it \n");
                }else{
                    $auxItem = 0;
                }

                if($auxItem == 0){
                    $size_name = "";

                    if($item->size_id == 9) {
                        $terms = $item->product_terms != null ? ' ('.$item->product_terms.')' : '';
                        $nombre = $item->quantity.'x'.' '.$item->product_name.' '.$terms;
                    }else{
                        $terms = $item->product_terms != null ? ' ('.$item->product_terms.')' : '';
                        $arSizeName = explode('(',$item->size_name);
                        $nombre = $item->quantity.'x'.' '.$item->product_name.' '.$terms.' '.$arSizeName[0];
                    }
                    $precio = number_format($item->price, 2, '.', '');
                    $order_value = $precio * $item->quantity;
                    if($order_value == 0 || $order_value == 0.00){
                        $order_value = "";
                    }
                    $index ++;
                    // Divide la línea en tres partes
                    $parteIzquierda = "$index >" . $nombre;
                    $parteCentro = "";
                    $parteDerecha = $order_value;
                
                    // Calcula la cantidad de espacios entre las partes
                    $espaciosCentro = self::CalculaEspacio($parteIzquierda,$parteDerecha);
                    
                    // Alineación a la izquierda
                    $impresora->text($parteIzquierda);
                    
                    // Alineación central (agrega espacios en blanco)
                    for ($i = 0; $i < $espaciosCentro; $i++) {
                        $parteCentro .= " ";
                    }
                    $impresora->text($parteCentro);
                    
                    // Alineación a la derecha
                    $impresora->text($parteDerecha);
                    $impresora->text("\n");
                }
            
    
            }

            // if($order->delivery_price != 0.00 && $order->delivery_price != "0.00"){
            //     $index ++;
            //     $parteIzquierda = "$index >" . " Delivery";
            //     $parteCentro = "";
            //     $parteDerecha = round($order->delivery_price, 2);
            //     // Alineación a la izquierda
            //     $impresora->text($parteIzquierda);
                
            //     // Calcula la cantidad de espacios entre las partes
            //     $espaciosCentro = self::CalculaEspacio($parteIzquierda,$parteDerecha);

            //     // Alineación central (agrega espacios en blanco)
            //     for ($i = 0; $i < $espaciosCentro; $i++) {
            //         $parteCentro .= " ";
            //     }
            //     $impresora->text($parteCentro);
                
            //     // Alineación a la derecha
            //     $impresora->text($parteDerecha);
            //     $impresora->text("\n");
            // }



    
            $impresora->setFont(PRINTER::FONT_B);
            $order_sin_impuesto = $order->total_price / ( 1 + 0.10 );
            $order_sin_impuesto =  round($order_sin_impuesto, 2);

            $msjOP = "OP. GRAVADAS: S/";
            $impresora->text($msjOP);
            $espaciosCentro = 0;
            $espaciosCentro = self::CalculaEspacio($msjOP,$order_sin_impuesto);
            $parteCentro = "";
            for ($i = 0; $i < $espaciosCentro; $i++) {
                $parteCentro .= " ";
            }
            $impresora->text($parteCentro);
            $impresora->text("$order_sin_impuesto");
            $impresora->text("\n");


            
            $msjIgv = "IGV (10%): S/";
            $impresora->text($msjIgv);
            $order_impuesto = $order_sin_impuesto * 0.10;
            $order_impuesto = round($order_impuesto, 2);
            $espaciosCentro = 0;
            $espaciosCentro = self::CalculaEspacio($msjIgv,$order_impuesto);
            $parteCentro = "";
            for ($i = 0; $i < $espaciosCentro; $i++) {
                $parteCentro .= " ";
            }
            $impresora->text($parteCentro);
            $impresora->text("$order_impuesto");
            $impresora->text("\n");
    
            $msjTotalPagar = "TOTAL A PAGAR S/";
            $impresora->text($msjTotalPagar);
            $totalPagar = number_format($order->total_price, 2, '.', '');
            $espaciosCentro = 0;
            $espaciosCentro = self::CalculaEspacio($msjTotalPagar,$totalPagar);
            $parteCentro = "";
            for ($i = 0; $i < $espaciosCentro; $i++) {
                $parteCentro .= " ";
            }
            $impresora->text($parteCentro);
            $impresora->text("$totalPagar");
            $impresora->text("\n");
            
            $arApp = ["ANDROID",'IOS','WEB'];
            $source_app_validate = strtoupper($order->source_app);
            $source_app = "";
            if(in_array($source_app_validate,$arApp)){
                $source_app = "APLICATIVO";
            }elseif($source_app_validate == "CALL"){
                $source_app = "CALLCENTER";
            }else{
                $source_app = "TIENDA";
            }
    
            $impresora->setFont(PRINTER::FONT_B);
            $impresora->text("================================================================\n");;
            $impresora->text("Información Adicional\n");
            $impresora->text("N° de pedido de tienda: $order->store_order_id \n"); //

            $forma_pago = "";
            $payment_method = strtoupper($order->payment_method);
            $paid = ""; //$order->paid == 1 ? "Pagado" : "Pendiente";
            if($payment_method == "CASH"){
                if($order->payment_received != null && $order->payment_received != ""){
                    if($order->payment_received == "Pago exacto" || $order->payment_received == "Pagará exacto"){
                        $forma_pago = "SOLES $totalPagar";
                    }else{
                        $vuelto = floatval($order->payment_received) - floatval($order->total_price);
                        $forma_pago = "SOLES $order->payment_received VUELTO:$vuelto";
                    }
                }else{
                    $forma_pago = "SOLES $totalPagar";
                }
            }elseif($payment_method == "CARD"){
                if($order->payment_mp != null && $order->payment_mp != ""){
                    $forma_pago = "Tarjeta - $order->payment_mp - $totalPagar";
                }else{
                    $forma_pago = "Tarjeta - $totalPagar";
                }
            }elseif($payment_method == "YAPE"){
                    $forma_pago = "YAPE - s/$totalPagar";
            }else{
                $change =  ($order->payment_with_cash + $order->payment_with_card) - $order->total_price;
                $forma_pago = "MIXTO - E: s/$order->payment_with_cash - T: s/$order->payment_with_card ($order->payment_mp) - V: s/$change";
            }

            $impresora->text("Forma de Pago: ".' '."$forma_pago"."\n");
            $impresora->text("$paid\n");
            $impresora->text("Caja: 01\n");
            $impresora->text("Canal: $source_app\n");
            $impresora->text("Cliente: $order->user_name\n");
            if($order->operator_name != "" && $order->operator_name != null){
                $impresora->text("Vendedor: $order->operator_name\n");
            }

            $payment_method = "";
            $payment_received = "no aplica";
            if($order->payment_method != "Yape"){
                $payment_method = $order->payment_method == "CARD" ? "Pago con tarjeta" :  "Pago al contado";
                if($order->payment_received != ""){
                    $payment_received = $order->payment_received;
                }
            }else{
                $payment_method = "Pago con $order->payment_method";
            }

            //si es delivery
            if($order->order_type == 1){
                $payment_way = "Online";
                $impresora->text("Tipo de pago: $payment_way\n");
                //$impresora->text("Metodo de pago: $payment_method - $paid\n");
                if($order->courier_name != null || $order->courier_name != ""){
                    $impresora->text("Motorizado: $order->courier_name\n");
                }
                $impresora->text("\n");
                $impresora->text("================================================================\n");
                $impresora->text("FIRMA:\n");
                $impresora->text("================================================================\n");
                $impresora->text("DNI:\n");
            }else{
                // $payment_way = "Contraentrega";

                // $impresora->text("Forma de pago: $payment_way\n");
                // $impresora->text("Metodo de pago: $payment_method - $paid\n");
                // $impresora->text("Efectivo: $payment_received\n");
            }
            $impresora->text("\n");
            $impresora->setJustification(Printer::JUSTIFY_CENTER);
            $impresora->text("Representación impresa de la \n$title_impresion\n");
            $impresora->text("Para consultar el comprobante ingresar a:\n");
    
    
            $testStr ="https://www.pizzaraul.com/";
            $impresora->setJustification(Printer::JUSTIFY_CENTER);
            $impresora->text("\n");
            $impresora->text("\n");
            $impresora -> qrCode($testStr, Printer::QR_ECLEVEL_L, 7);
            $impresora->text("\nhttps://www.pizzaraul.com/\n");
            $impresora->cut();
            $impresora->close();
            return response()->json(["message" => "IMPRESION DE TICKET DE VENTA"], 200 );
        } catch (\Throwable $th) {
            //throw $th;
            // Capturar mensaje del error
            $errorMessage = $th->getMessage();
            // Capturar el archivo donde ocurrió el error
            $errorFile = $th->getFile();
            // Capturar la línea donde ocurrió el error
            $errorLine = $th->getLine();
            $data = [
                "message" => "Error: {$errorMessage} en el archivo {$errorFile} en la línea {$errorLine}"
            ];
            Log::channel('stderr')->info("Error: {$errorMessage} en el archivo {$errorFile} en la línea {$errorLine}");
            return  $data;
        }
    }

    public static function ticketCocina($order,$items,$printer){
        $order = (object) $order;
        $items = (object) $items;
        $mesa = $order->user_table == null ? "" : " --- " . $order->user_table;
        $type_delivery = "";
        switch ($order->order_type) {
            case '2':
                # code...
                $type_delivery = "RECOJO";
                break;
            
            case '3':
                # code...
                $type_delivery = "SALON";
                break;
            
            default:
                # code...
                $type_delivery = "DELIVERY";
                break;
        }

        $nombreImpresora = "$printer";
        $numero = "";
        if (isset($order->store_order_id)) {
            # code...
            $numero = $order->store_order_id != null ? $order->store_order_id : "";
        }
        $connector = new WindowsPrintConnector($nombreImpresora);
        $impresora = new Printer($connector);
        $impresora->setJustification(Printer::JUSTIFY_LEFT);
        $impresora->setFont(PRINTER::FONT_A);
        $impresora->setTextSize(2,2);
        $impresora->setEmphasis(true);
        $impresora->text($type_delivery . $mesa);
        $impresora->setTextSize(1,1);
        $impresora->text("\n");
        $impresora->text("N° ORDEN: $numero". "   -  ".$order->source_app);
        $impresora->setJustification(Printer::JUSTIFY_CENTER);
        $impresora->setJustification(Printer::JUSTIFY_LEFT);
        $impresora->setEmphasis(true);
        $impresora->text("\n");
        $date =  date('d/m/Y', strtotime($order->created_at));
        $horaActual = date('H:i:s', strtotime($order->created_at));
        //contenido source
        $uppercase = strtoupper($order->user_name);
        $impresora->text("FECHA :$date HORA:$horaActual\n");
        $impresora->setEmphasis(true);
        $impresora->text("CLIENTE: $uppercase");
        $impresora->text("\n\n");
        $impresora->setTextSize(1,1);
        $index = 0;
        $auxItem = 0;
        $auxPromId = 0;

        foreach ($items as $item) {
            $item = (object) $item;
            $impresora->setFont(PRINTER::FONT_A);
            if(($item->item_id != $auxItem ) && ($item->promotion_id != null)){
                $index ++;
                $auxItem = $item->item_id;
                $auxPromId = $item->promotion_id;
                $nombre = mb_substr($item->promotion_name, 0, 40);
                $nombre = strtoupper($nombre);
                $precio = number_format($order->total_price, 2, '.', '');   
                // Divide la línea en tres partes
                $parteIzquierda = $nombre;// "$index >" . $nombre;
                $parteCentro = "";
                $parteDerecha = $precio;
            
                // Calcula la cantidad de espacios entre las partes
                $espaciosCentro = self::CalculaEspacio($parteIzquierda,$parteDerecha);
                
                // Alineación a la izquierda
                $impresora->text($parteIzquierda);
                
                // Alineación central (agrega espacios en blanco)
                for ($i = 0; $i < $espaciosCentro; $i++) {
                    $parteCentro .= " ";
                }
                $impresora->text($parteCentro);
                
                // Alineación a la derecha
                $impresora->text("\n");
                
                $terms = $item->product_terms != null ? ' ('.$item->product_terms.')' : '';
                $q_promo = $item->q_prom;
                if($item->size_id == 9) {
                    $arApp = ["ANDROID",'IOS','WEB'];
                    $source_app = strtoupper($order->source_app);
                    $q_total = 0;
                    if(in_array($source_app,$arApp) && $q_promo != 0){
                        $q_total = $item->quantity;
                    }else{
                        $q_total = $item->quantity;
                    }

                    $nombre_it = $q_total.''.' '.$item->product_name.$terms;
                }else{
                    $arApp = ["ANDROID",'IOS','WEB'];
                    $source_app = strtoupper($order->source_app);
                    $q_total = 0;
                    if(in_array($source_app,$arApp)){
                        $q_total = $item->quantity;
                    }else{
                        $q_total = $item->quantity;
                    }
                    $arSizeName = explode('(',$item->size_name);
                    $nombre_it = $q_total.''.' '.$item->product_name.' '.$arSizeName[0].$terms;
                }
                $nombre_it = strtoupper($nombre_it);
                //$impresora->setFont(PRINTER::FONT_B);
                $impresora->text("$nombre_it \n");

            }elseif($item->promotion_id == $auxPromId && $item->promotion_id != null){
                $terms = $item->product_terms != null ? ' ('.$item->product_terms.')' : '';
                $q_promo = $item->q_prom;
                if($item->size_id == 9) {
                    $arApp = ["ANDROID",'IOS','WEB'];
                    $source_app = strtoupper($order->source_app);
                    $q_total = 0;
                    if(in_array($source_app,$arApp) && $q_promo != 0){
                        $q_total = $item->quantity;
                    }else{
                        $q_total = $item->quantity;
                    }

                    $nombre_it = $q_total.''.' '.$item->product_name.$terms;
                }else{
                    $arApp = ["ANDROID",'IOS','WEB'];
                    $source_app = strtoupper($order->source_app);
                    $q_total = 0;
                    if(in_array($source_app,$arApp) && $q_promo != 0){
                        $q_total = $item->quantity;
                    }else{
                        $q_total = $item->quantity;
                    }
                    $arSizeName = explode('(',$item->size_name);
                    $nombre_it = $q_total.''.' '.$item->product_name.' '.$arSizeName[0].$terms;
                }
                $nombre_it = strtoupper($nombre_it);
                //$impresora->setFont(PRINTER::FONT_B);
                $impresora->text("$nombre_it \n");
            }else{
                $auxItem = 0;
            }

            if($auxItem == 0){
                $terms = $item->product_terms != null ? ' ('.$item->product_terms.')' : '';
                if($item->size_id == 9) {
                    $nombre = $item->quantity.''.' '.$item->product_name.$terms;
                }else{
                    $arSizeName = explode('(',$item->size_name);
                    $nombre = $item->quantity.''.' '.$item->product_name.' '.$arSizeName[0].$terms;
                }
                $nombre = strtoupper($nombre);
                $precio = number_format($item->price, 2, '.', '');
                $index ++;
                // Divide la línea en tres partes
                $parteIzquierda = $nombre;//"$index >" . $nombre;
                $parteCentro = "";
                $parteDerecha = $precio;
            
                // Calcula la cantidad de espacios entre las partes
                $espaciosCentro = self::CalculaEspacio($parteIzquierda,$parteDerecha);
                
                // Alineación a la izquierda
                $impresora->text($parteIzquierda);
                
                // Alineación central (agrega espacios en blanco)
                for ($i = 0; $i < $espaciosCentro; $i++) {
                    $parteCentro .= " ";
                }
                $impresora->text($parteCentro);
                
                // Alineación a la derecha
                //$impresora->text($parteDerecha);
                $impresora->text("\n");
            }
        

            
            
        }
        $impresora->setTextSize(1,1);
        $impresora->setFont(PRINTER::FONT_A);
        $impresora->text("\n");
        $impresora->text("OBS: \n");
        $impresora->text("$order->observation\n");
        
        $forma_pago = "";
        $payment_method = strtoupper($order->payment_method);
        $order->total_price = number_format($order->total_price, 2, '.', '');   
        if($payment_method == "CASH"){
            if($order->payment_received != null && $order->payment_received != ""){
                if($order->payment_received == "Pago exacto"){
                    $forma_pago = "SOLES $order->total_price";
                }else{
                    $vuelto = floatval($order->payment_received) - floatval($order->total_price);
                    $forma_pago = "SOLES $order->payment_received VUELTO:$vuelto";
                }
            }else{
                $forma_pago = "SOLES $order->total_price";
            }
        }elseif($payment_method == "CARD"){
            if($order->payment_mp != null && $order->payment_mp != ""){
                $forma_pago = "Tarjeta - $order->payment_mp - s/$order->total_price";
            }else{
                $forma_pago = "Tarjeta - s/$order->total_price";
            }
        }elseif($payment_method == "YAPE"){
                $forma_pago = "YAPE - s/$order->total_price";
        }else{
            $change =  ($order->payment_with_cash + $order->payment_with_card) - $order->total_price;
            $forma_pago = "MIXTO - E: s/$order->payment_with_cash - T: s/$order->payment_with_card ($order->payment_mp) - V: s/$change";
        }
        
        $impresora->text("------------------------------------------------\n");
        $impresora->text("FORMA DE PAGO".' '."$forma_pago"."\n");
        $is_payment = $order->paid == 1 ? 'PAGADO' : 'POR PAGAR';
        $impresora->text("$is_payment\n");


        //$testStr ="https://www.pizzaraul.work/";
        $impresora->setJustification(Printer::JUSTIFY_CENTER);
        $impresora->text("\n");
        $impresora->feed(2);
        $impresora->cut();
        $impresora->close();
    }

    public function ticketDeliveryDriver($order,$items,$store,$printer) {
        $nombreImpresora = "$printer";
        $order = (object) $order;
        $items = (object) $items;
        $store = (object) $store;
        //$connector = new WindowsPrintConnector($nombreImpresora);
        try {
            $connector = new WindowsPrintConnector($nombreImpresora);
            $impresora = new Printer($connector);
            $date = date('d-m-Y');
            $horaActual = date('h:i:s A');
            $impresora->setFont(PRINTER::FONT_B);
            $impresora->setJustification(Printer::JUSTIFY_CENTER);
            $impresora->setTextSize(1, 1);
            $impresora->setEmphasis(true);
            $impresora->text("PIZZA RAUL\n");   
            $impresora->setFont(PRINTER::FONT_B);
            $impresora->setEmphasis(false);
            $impresora->text("================================================================\n");
            $impresora->setJustification(Printer::JUSTIFY_LEFT);
            $impresora->setFont(PRINTER::FONT_B);
            //contenido source
            $impresora->text("PEDIDO N° $order->store_order_id\n"); 
            $impresora->text("Motorizado: $order->courier_name\n");
            $impresora->text("Orden de compra: $order->id\n");
            $impresora->text("Caja: 01\n FECHA: $date HORA : $horaActual\n");
            $impresora->setTextSize(1, 1);
            $impresora->text("================================================================\n");
            $impresora->setJustification(Printer::JUSTIFY_LEFT);
            //$impresora->text("Referencia: $order->reference \n");
            $impresora->text("================================================================\n");
    
            $impresora->text("i     Descripción                                             s/\n");
            $impresora->text("----------------------------------------------------------------\n");
    
            $index = 0;
            $auxItem = 0;
            $auxPromId = 0;
    
            foreach ($items as $item) {
                $item = (object) $item;
                $impresora->setFont(PRINTER::FONT_B);
                if(($item->item_id != $auxItem ) && ($item->promotion_id != null) ){
                    $index ++;
                    $auxItem = $item->item_id;
                    $auxPromId = $item->promotion_id;
                    $nombre = $item->promotion_name;
                    $precio = number_format($item->price, 2, '.', ''); 
                    $qprom = $index." > ".$item->q_prom."x ";
                    $order_value = $precio * $item->q_prom;
                    // Divide la línea en tres partes
                    $parteIzquierda = $qprom . $nombre;
                    $parteCentro = "";
                    $parteDerecha = $order_value;
                
                    // Calcula la cantidad de espacios entre las partes
                    $espaciosCentro = self::CalculaEspacio($parteIzquierda,$parteDerecha);
                    
                    // Alineación a la izquierda
                    $impresora->text($parteIzquierda);
                    
                    // Alineación central (agrega espacios en blanco)
                    for ($i = 0; $i < $espaciosCentro; $i++) {
                        $parteCentro .= " ";
                    }
                    $impresora->text($parteCentro);
                    
                    // Alineación a la derecha
                    $impresora->text($parteDerecha);
                    $impresora->text("\n");

                    
                    $terms = $item->product_terms != null ? ' ('.$item->product_terms.')' : '';
                    $q_promo = $item->q_prom;
                    if($item->size_id == 9) {
                        $arApp = ["ANDROID",'IOS','WEB'];
                        $source_app = strtoupper($order->source_app);
                        $q_total = 0;
                        if(in_array($source_app,$arApp) && $q_promo != 0){
                            $q_total = $item->quantity;
                        }else{
                            $q_total = $item->quantity;
                        }

                        $nombre_it = $q_total.'x'.' '.$item->product_name.' '.$terms;
                    }else{
                        $arApp = ["ANDROID",'IOS','WEB'];
                        $source_app = strtoupper($order->source_app);
                        $q_total = 0;
                        if(in_array($source_app,$arApp) && $q_promo != 0){
                            $q_total = $item->quantity;
                        }else{
                            $q_total = $item->quantity;
                        }
                        $arSizeName = explode('(',$item->size_name);
                        $nombre_it = $q_total.'x'.' '.$item->product_name.' '.$terms.' '.$arSizeName[0];
                    }

                    //$nombre_it = $item->quantity.'x'.' '.$item->product_name.' '.$item->size_name;
                    $impresora->setFont(PRINTER::FONT_B);
                    $impresora->text("  >> $nombre_it \n");

                }elseif($item->promotion_id == $auxPromId && $item->promotion_id != null){
                    
                    $terms = $item->product_terms != null ? ' ('.$item->product_terms.')' : '';
                    $q_promo = $item->q_prom;
                    if($item->size_id == 9) {
                        $arApp = ["ANDROID",'IOS','WEB'];
                        $source_app = strtoupper($order->source_app);
                        $q_total = 0;
                        if(in_array($source_app,$arApp) && $q_promo != 0){
                            $q_total = $item->quantity;
                        }else{
                            $q_total = $item->quantity;
                        }

                        $nombre_it = $q_total.'x'.' '.$item->product_name.' '.$terms;
                    }else{
                        $arApp = ["ANDROID",'IOS','WEB'];
                        $source_app = strtoupper($order->source_app);
                        $q_total = 0;
                        if(in_array($source_app,$arApp) && $q_promo != 0){
                            $q_total = $item->quantity;
                        }else{
                            $q_total = $item->quantity;
                        }
                        $arSizeName = explode('(',$item->size_name);
                        $nombre_it = $q_total.'x'.' '.$item->product_name.' '.$terms.' '.$arSizeName[0];
                    }

                    //$nombre_it = $item->quantity.'x'.' '.$item->product_name.' '.$item->size_name;
                    $impresora->setFont(PRINTER::FONT_B);
                    $impresora->text("  >> $nombre_it \n");
                }else{
                    $auxItem = 0;
                }

                if($auxItem == 0){
                    $size_name = "";

                    if($item->size_id == 9) {
                        $terms = $item->product_terms != null ? ' ('.$item->product_terms.')' : '';
                        $nombre = $item->quantity.'x'.' '.$item->product_name.' '.$terms;
                    }else{
                        $terms = $item->product_terms != null ? ' ('.$item->product_terms.')' : '';
                        $arSizeName = explode('(',$item->size_name);
                        $nombre = $item->quantity.'x'.' '.$item->product_name.' '.$terms.' '.$arSizeName[0];
                    }
                    $precio = number_format($item->price, 2, '.', '');
                    $order_value = $precio * $item->quantity;
                    if($order_value == 0 || $order_value == 0.00){
                        $order_value = "";
                    }
                    $index ++;
                    // Divide la línea en tres partes
                    $parteIzquierda = "$index >" . $nombre;
                    $parteCentro = "";
                    $parteDerecha = $order_value;
                
                    // Calcula la cantidad de espacios entre las partes
                    $espaciosCentro = self::CalculaEspacio($parteIzquierda,$parteDerecha);
                    
                    // Alineación a la izquierda
                    $impresora->text($parteIzquierda);
                    
                    // Alineación central (agrega espacios en blanco)
                    for ($i = 0; $i < $espaciosCentro; $i++) {
                        $parteCentro .= " ";
                    }
                    $impresora->text($parteCentro);
                    
                    // Alineación a la derecha
                    $impresora->text($parteDerecha);
                    $impresora->text("\n");
                }
            
    
            }
    
            $impresora->setFont(PRINTER::FONT_B);
            $order_sin_impuesto = $order->total_price / ( 1 + 0.10 );
            $order_sin_impuesto =  round($order_sin_impuesto, 2);

            $msjOP = "OP. GRAVADAS: S/";
            $impresora->text($msjOP);
            $espaciosCentro = 0;
            $espaciosCentro = self::CalculaEspacio($msjOP,$order_sin_impuesto);
            $parteCentro = "";
            for ($i = 0; $i < $espaciosCentro; $i++) {
                $parteCentro .= " ";
            }
            $impresora->text($parteCentro);
            $impresora->text("$order_sin_impuesto");
            $impresora->text("\n");


            
            $msjIgv = "IGV (10%): S/";
            $impresora->text($msjIgv);
            $order_impuesto = $order_sin_impuesto * 0.10;
            $order_impuesto = round($order_impuesto, 2);
            $espaciosCentro = 0;
            $espaciosCentro = self::CalculaEspacio($msjIgv,$order_impuesto);
            $parteCentro = "";
            for ($i = 0; $i < $espaciosCentro; $i++) {
                $parteCentro .= " ";
            }
            $impresora->text($parteCentro);
            $impresora->text("$order_impuesto");
            $impresora->text("\n");
    
            $msjTotalPagar = "TOTAL A PAGAR S/";
            $impresora->text($msjTotalPagar);
            $totalPagar = number_format($order->total_price, 2, '.', '');
            $espaciosCentro = 0;
            $espaciosCentro = self::CalculaEspacio($msjTotalPagar,$totalPagar);
            $parteCentro = "";
            for ($i = 0; $i < $espaciosCentro; $i++) {
                $parteCentro .= " ";
            }
            $impresora->text($parteCentro);
            $impresora->text("$totalPagar");
            $impresora->text("\n");
    
            $impresora->setFont(PRINTER::FONT_B);
            $impresora->text("================================================================\n");;
            $impresora->text("Información Adicional\n");
            $impresora->text("N° de pedido de tienda: $order->store_order_id\n");

            $forma_pago = "";
            $payment_method = strtoupper($order->payment_method);
            $order->total_price = number_format($order->total_price, 2, '.', '');
            if($payment_method == "CASH"){
                if($order->payment_received != null && $order->payment_received != ""){
                    if($order->payment_received == "Pago exacto" || $order->payment_received == "Pagará exacto"){
                        $forma_pago = "SOLES $order->total_price";
                    }else{
                        $vuelto = floatval($order->payment_received) - floatval($order->total_price);
                        $forma_pago = "SOLES $order->payment_received VUELTO:$vuelto";
                    }
                }else{
                    $forma_pago = "SOLES $order->total_price";
                }
            }elseif($payment_method == "CARD"){
                if($order->payment_mp != null && $order->payment_mp != ""){
                    $forma_pago = "Tarjeta - $order->payment_mp - s/$order->total_price";
                }else{
                    $forma_pago = "Tarjeta - s/$order->total_price";
                }
            }elseif($payment_method == "YAPE"){
                    $forma_pago = "YAPE - s/$order->total_price";
            }else{
                $order->payment_with_cash = number_format($order->payment_with_cash, 2, '.', '');
                $order->payment_with_card = number_format($order->payment_with_card, 2, '.', '');
                $change =  ($order->payment_with_cash + $order->payment_with_card) - $order->total_price;
                $forma_pago = "MIXTO - E: s/$order->payment_with_cash - T: s/$order->payment_with_card ($order->payment_mp) - V: s/$change";
            }

            $impresora->text("FORMA DE PAGO".' '."$forma_pago"."\n");
            $impresora->text("CLIENTE: $order->user_name\n");
            $impresora->text("DIRECCIÓN: $order->street_name $order->street_number\n");
            $impresora->text("REFERENCIA: \n");
            $impresora->text("$order->reference\n");
            $impresora->text("TELEFONO: $order->user_phone\n");
            //si es delivery
            if($order->order_type == 1){
                $payment_way = "Online";
                //$impresora->text("TIPO DE PAGO: $payment_way\n");
            }

            $impresora->text("\n");
            $impresora->cut();
            $impresora->close();
            return response()->json(["message" => "IMPRESION DE TICKET DE VENTA"], 200 );
        } catch (\Throwable $th) {
            //throw $th;
            // Capturar mensaje del error
            $errorMessage = $th->getMessage();
            // Capturar el archivo donde ocurrió el error
            $errorFile = $th->getFile();
            // Capturar la línea donde ocurrió el error
            $errorLine = $th->getLine();
            $data = [
                "message" => "Error: {$errorMessage} en el archivo {$errorFile} en la línea {$errorLine}"
            ];
            Log::channel('stderr')->info("Error: {$errorMessage} en el archivo {$errorFile} en la línea {$errorLine}");
            return  $data;
        }
    }
    
    public function ticketCierreCaja($store,$apertura_s,$suma_S,$ventas,$transactions_S,$usuario,$store_balance,$mercaderia,$printer) {
        $nombreImpresora = "$printer";
        $store = (object) $store;
        $suma_S = (object) $suma_S;
        if($ventas != null){
            $ventas = (object) $ventas;
        }else{
            $ventas = null;
        }
        $usuario = (object) $usuario;
        $transactions_S = (object) $transactions_S;
        $store_balance = (object) $store_balance;
        $mercaderia = (object) $mercaderia;
        $apertura_s = (object) $apertura_s;
        try {
            $connector = new WindowsPrintConnector($nombreImpresora);
            $impresora = new Printer($connector);
            $date = date('d/m/Y');
            $horaActual = date('h:i:s A');
            $impresora->setEmphasis(true);
            $impresora->setFont(PRINTER::FONT_A);
            
            $impresora->setJustification(Printer::JUSTIFY_CENTER);
            $impresora->text("$store->title\n");   
            $impresora->text("RUC: $store->nro_ruc\n");   
            $impresora->text("$store->razon_social\n");   
            $impresora->text("\n");   
            $impresora->setFont(PRINTER::FONT_B);
            $impresora->setJustification(Printer::JUSTIFY_LEFT);

            $impresora->text("Cierre de caja");
            $impresora->text("\n");
            $impresora->text("\n");

            $impresora->setJustification(Printer::JUSTIFY_LEFT);
            
            $store_name = "TIENDA ".$store->title;
            $impresora->text("$store_name\n");   
            
            $date_string = "FECHA: ".$date." Hora: ".$horaActual;
            $impresora->text("$date_string\n");           
            

            $impresora->setJustification(Printer::JUSTIFY_LEFT);

            $impresora->text("\n");
            //-------------------------------------

            $name = "SALDO INICIAL: ";
            $maxLength = 45;
            $shortname = substr($name, 0, $maxLength);
            $limit_first = 48;
            $first = str_pad($shortname, $limit_first);

            $name2 = $apertura_s->amount;
            $name2 = number_format($name2, 2);
            $maxLength = 8;
            $shortname = substr($name2, 0, $maxLength);
            $limit_first = 8;
            $two = str_pad($shortname, $limit_first);

            $saldo_inicial = $first.$two;
            $impresora->text("$saldo_inicial\n");   

            //-------------------------------------

            $name = "VENTAS: ";
            $maxLength = 45;
            $shortname = substr($name, 0, $maxLength);
            $limit_first = 48;
            $first = str_pad($shortname, $limit_first);

            $name2 = $store_balance->balance_opening;
            $name2 = number_format($name2, 2);
            $venta_save = $name2;
            $maxLength = 8;
            $shortname = substr($name2, 0, $maxLength);
            $limit_first = 8;
            $two = str_pad($shortname, $limit_first);

            $ventas_st = $first.$two;
            $impresora->text("$ventas_st\n");   

            //-------------------------------------

            $name = "DEPOSITO EFECTIVO: ";
            $maxLength = 45;
            $shortname = substr($name, 0, $maxLength);
            $limit_first = 48;
            $first = str_pad($shortname, $limit_first);

            if($ventas == null){
                $name2 = 0.00;
                $name2 = number_format($name2, 2);
            }else{
                $name2 = $ventas->amount;
                $name2 = number_format($name2, 2);
            }
            $maxLength = 8;
            $shortname = substr($name2, 0, $maxLength);
            $limit_first = 8;
            $two = str_pad($shortname, $limit_first);

            $ventas_st = $first.$two;
            $impresora->text("$ventas_st\n");   

            //-------------------------------------

            $name = "SALDO EN CAJA: ";
            $maxLength = 45;
            $shortname = substr($name, 0, $maxLength);
            $limit_first = 48;
            $first = str_pad($shortname, $limit_first);

            $name2 = $store_balance->sales_total;
            $name2 = number_format($name2, 2);
            $maxLength = 8;
            $shortname = substr($name2, 0, $maxLength);
            $limit_first = 8;
            $two = str_pad($shortname, $limit_first);

            $ventas_st = $first.$two;
            $impresora->text("$ventas_st\n");   

            //-------------------------------------

            $impresora->text("\n");
            $impresora->setEmphasis(true);
            $name = "MOVIMIENTOS DE CAJA: EGRESOS ";
            $maxLength = 45;
            $shortname = substr($name, 0, $maxLength);
            $limit_first = 48;
            $first = str_pad($shortname, $limit_first);

            $impresora->text("$first\n");   
            $impresora->text("\n");
            $impresora->setEmphasis(true);

            foreach ($transactions_S as $key) {
                # code...
                $key = (object) $key;
                $name = "$key->category_trx_name";
                $maxLength = 45;
                $shortname = substr($name, 0, $maxLength);
                $limit_first = 48;
                $first = str_pad($shortname, $limit_first);

                $name2 = $key->amount;
                $name2 = number_format($name2, 2);
                $maxLength = 8;
                $shortname = substr($name2, 0, $maxLength);
                $limit_first = 8;
                $two = str_pad($shortname, $limit_first);
                $ventas_st = $first.$two;
                $impresora->text("$ventas_st\n");   
            }

            //----------------MERCADERIA---------------------

            $impresora->text("\n");
            $impresora->setEmphasis(true);
            $name = "MERCADERIA ";
            $maxLength = 45;
            $shortname = substr($name, 0, $maxLength);
            $limit_first = 48;
            $first = str_pad($shortname, $limit_first);

            $impresora->text("$first\n");   
            $impresora->text("\n");
            $impresora->setEmphasis(true);
            
            //-------------------------------------

            $name = "SALDO INICIAL EN MERCADERIA: ";
            $maxLength = 45;
            $shortname = substr($name, 0, $maxLength);
            $limit_first = 48;
            $first = str_pad($shortname, $limit_first);

            $name2 = $mercaderia->saldoInicial;
            $name2 = number_format($name2, 2);
            $maxLength = 8;
            $shortname = substr($name2, 0, $maxLength);
            $limit_first = 8;
            $two = str_pad($shortname, $limit_first);

            $ventas_st = $first.$two;
            $impresora->text("$ventas_st\n");   

            //-------------------------------------

            $name = "DESPACHO ALMACEN: ";
            $maxLength = 45;
            $shortname = substr($name, 0, $maxLength);
            $limit_first = 48;
            $first = str_pad($shortname, $limit_first);

            $name2 = $mercaderia->totalDeAlmacenes;
            $name2 = number_format($name2, 2);
            $maxLength = 8;
            $shortname = substr($name2, 0, $maxLength);
            $limit_first = 8;
            $two = str_pad($shortname, $limit_first);

            $ventas_st = $first.$two;
            $impresora->text("$ventas_st\n");   

            //-------------------------------------

            $name = "DESPACHO TIENDA: ";
            $maxLength = 45;
            $shortname = substr($name, 0, $maxLength);
            $limit_first = 48;
            $first = str_pad($shortname, $limit_first);

            $name2 = $mercaderia->totalEntradaDeTienda;
            $name2 = number_format($name2, 2);
            $maxLength = 8;
            $shortname = substr($name2, 0, $maxLength);
            $limit_first = 8;
            $two = str_pad($shortname, $limit_first);

            $ventas_st = $first.$two;
            $impresora->text("$ventas_st\n");   

            //-------------------------------------

            $name = "SALIDA TIENDA: ";
            $maxLength = 45;
            $shortname = substr($name, 0, $maxLength);
            $limit_first = 48;
            $first = str_pad($shortname, $limit_first);

            $name2 = $mercaderia->totalDespachoATienda;
            $name2 = number_format($name2, 2);
            $maxLength = 8;
            $shortname = substr($name2, 0, $maxLength);
            $limit_first = 8;
            $two = str_pad($shortname, $limit_first);

            $ventas_st = $first.$two;
            $impresora->text("$ventas_st\n");   



            //-------------------------------------

            $name = "CONSUMO: ";
            $maxLength = 45;
            $shortname = substr($name, 0, $maxLength);
            $limit_first = 48;
            $first = str_pad($shortname, $limit_first);

            

            $name2 = $mercaderia->saldoInicial + $mercaderia->totalDeAlmacenes  + $mercaderia->totalEntradaDeTienda - $mercaderia->totalDespachoATienda - $mercaderia->saldoFinal;

            $name2 = doubleval($name2);
            $name2 = number_format($name2, 2);
            $consumo_save = $name2;
            $maxLength = 8;
            $shortname = substr($name2, 0, $maxLength);
            $limit_first = 8;
            $two = str_pad($shortname, $limit_first);

            $ventas_st = $first.$two;
            $impresora->text("$ventas_st\n");   

            //-------------------------------------

            $impresora->setEmphasis(true);
            $name = "SALDO FINAL MERCADERIA: ";
            $maxLength = 45;
            $shortname = substr($name, 0, $maxLength);
            $limit_first = 48;
            $first = str_pad($shortname, $limit_first);

            $name2 = doubleval($mercaderia->saldoFinal);
            $name2 = number_format($name2, 2);
            $maxLength = 8;
            $shortname = substr($name2, 0, $maxLength);
            $limit_first = 8;
            $two = str_pad($shortname, $limit_first);

            $ventas_st = $first.$two;
            $impresora->text("$ventas_st\n");   

            //-------------------------------------

            $impresora->text("\n");   
            $impresora->setEmphasis(true);
            $name = "RATIO DE TIENDA: ";
            $maxLength = 45;
            $shortname = substr($name, 0, $maxLength);
            $limit_first = 48;
            $first = str_pad($shortname, $limit_first);

            $name2 = (doubleval($consumo_save) / doubleval($venta_save)) * 100 ;
            $name2 = doubleval($name2);
            //$name2 = number_format($name2, 2) . "%";
            $maxLength = 8;
            $shortname = substr($name2, 0, $maxLength);
            $limit_first = 8;
            $two = str_pad($shortname, $limit_first);

            $ventas_st = $first.$two;
            $impresora->text("$ventas_st\n");   


            //-------------------------------------

            $impresora->text("\n");
            $impresora->cut();
            $impresora->close();
            return response()->json(["message" => "IMPRESION DE TICKET DE VENTA"], 200 );
        } catch (\Throwable $th) {
            //throw $th;
            // Capturar mensaje del error
            $errorMessage = $th->getMessage();
            // Capturar el archivo donde ocurrió el error
            $errorFile = $th->getFile();
            // Capturar la línea donde ocurrió el error
            $errorLine = $th->getLine();
            $data = [
                "message" => "Error: {$errorMessage} en el archivo {$errorFile} en la línea {$errorLine}"
            ];
            Log::channel('stderr')->info("Error: {$errorMessage} en el archivo {$errorFile} en la línea {$errorLine}");
            return  $data;
        }
    }

    public function ticketPaloteo($store,$data,$printer){
        $store = (object) $store;
        $data = (object) $data;
        $nombreImpresora = "$printer";
        try {
            $connector = new WindowsPrintConnector($nombreImpresora);
            $impresora = new Printer($connector);
            
            $impresora->setJustification(Printer::JUSTIFY_CENTER);
            $impresora->text("$store->title\n");   
            $impresora->text("RUC: $store->nro_ruc\n");   
            $impresora->text("$store->razon_social\n");   
            $impresora->text("\n");   
            $impresora->setFont(PRINTER::FONT_B);
            $impresora->setJustification(Printer::JUSTIFY_LEFT);

            $impresora->text("Paloteo de ventas");
            $impresora->text("\n");
            $impresora->text("\n");
            $auxTitleCat = "";
            $totalPaloteo = 0.00;
            foreach ($data as $key) {
                # code...
                $key = (object) $key;
                $impresora->setEmphasis(true);
                if($auxTitleCat == ""){
                    $auxTitleCat = $key->Categoria;
                    $impresora->text($auxTitleCat);
                    $impresora->text("\n");
                }else{
                    if($auxTitleCat != $key->Categoria) {
                        $auxTitleCat = $key->Categoria;
                        $impresora->text("\n");
                        $impresora->text("\n");
                        $impresora->text($auxTitleCat);
                        $impresora->text("\n");
                    }
                }
                $impresora->setEmphasis(false);
                ##product name
                $impresora->text("\n");
                $size_name = "";
                if($key->Tamano != "Sin tamano"){
                    $size_name = $key->Tamano;
                }
                $name = $key->Descripcion."  ".$size_name;
                $maxLength = 45;
                $shortname = substr($name, 0, $maxLength);
                $limit_first = 48;
                $first = str_pad($shortname, $limit_first);
                ##size -- 6 -- limit 10
                // $sizename = $key->Tamano;
                // $maxLengthSize = 3;
                // $shortsizename = substr($sizename, 0, $maxLengthSize);
                // $limit_size = 6;
                // $two = str_pad($shortsizename, $limit_size);
                ##quantity
                $quatity_name = $key->Cantidad;
                $maxLengthquatity = 3;
                $shortnamequatity = substr($quatity_name, 0, $maxLengthquatity);
                $limit_quatity = 6;
                $three = str_pad($shortnamequatity, $limit_quatity);
                ##unit price
                /* $unit_price = 0.00;
                if(intval($key->Cantidad) > 0){
                    $unit_price = ($key->PrecioTotal) / $key->Cantidad;
                }
                $formattedValue = number_format($unit_price, 2, '.', '');
                $maxLengthunit_price = 6;
                $shortnameunit_price = substr($formattedValue, 0, $maxLengthunit_price);
                $limit_quatity = 8;
                $four = str_pad($shortnameunit_price, $limit_quatity); */

                ##total_price
                $formattedValue = number_format($key->PrecioTotal, 2, '.', '');
                $maxLengthunit_price = 6;
                $shortnameunit_price = substr($formattedValue, 0, $maxLengthunit_price);
                $limit_quatity = 8;
                $five = str_pad($shortnameunit_price, $limit_quatity);
                $totalPaloteo = $totalPaloteo + $key->PrecioTotal;
                // final text
                $final_text = $first.$three.$five;
                $impresora->text($final_text);

            }

            $impresora->setFont(PRINTER::FONT_A);
            $impresora->setEmphasis(true);
            $name = "Total";
            $maxLength = 5;
            $shortname = substr($name, 0, $maxLength);
            $limit_first = 35;
            $first = str_pad($shortname, $limit_first);

            $formattedValue = number_format($totalPaloteo, 2, '.', '');
            $maxLengthunit_price = 6;
            $shortnameunit_price = substr($formattedValue, 0, $maxLengthunit_price);
            $limit_quatity = 8;
            $five = str_pad($shortnameunit_price, $limit_quatity);
            $five = "s/. ".$five;
            $impresora->text("\n");
            $impresora->text("\n");
            $final_text = $first.$five;
            $impresora->text($final_text);
            $impresora->text("\n");
            $impresora->feed(2);
            $impresora->cut();
            $impresora->close();
            return response()->json(["message" => "IMPRESION DE TICKET DE VENTA"], 200 );
        } catch (\Throwable $th) {
            //throw $th;
            // Capturar mensaje del error
            $errorMessage = $th->getMessage();
            // Capturar el archivo donde ocurrió el error
            $errorFile = $th->getFile();
            // Capturar la línea donde ocurrió el error
            $errorLine = $th->getLine();
            $data = [
                "message" => "Error: {$errorMessage} en el archivo {$errorFile} en la línea {$errorLine}"
            ];
            Log::channel('stderr')->info("Error: {$errorMessage} en el archivo {$errorFile} en la línea {$errorLine}");
            return  $data;
        }
    }

    public function ticketInventario($store,$data,$printer){
        $store = (object) $store;
        $nombreImpresora = "$printer";
        try {
            $connector = new WindowsPrintConnector($nombreImpresora);
            $impresora = new Printer($connector);
            
            $impresora->setJustification(Printer::JUSTIFY_CENTER);

            $impresora->text("$store->title\n");   
            $impresora->text("RUC: $store->nro_ruc\n");   
            $impresora->text("$store->razon_social\n");   
            $impresora->setFont(PRINTER::FONT_B);
            $impresora->setJustification(Printer::JUSTIFY_LEFT);
            $impresora->text("\n");   
            $impresora->text("Inventario");
            $impresora->text("\n");
            $impresora->text("\n");
            $auxTitleCat = "";
            foreach ($data as $key) {
                # code...
                $key = (object) $key;

                $impresora->setEmphasis(true);
                if($auxTitleCat == ""){
                    $auxTitleCat = $key->categoryName;
                    $impresora->text($auxTitleCat);
                    $impresora->text("\n");
                }else{
                    if($auxTitleCat != $key->categoryName) {
                        $auxTitleCat = $key->categoryName;
                        $impresora->text("\n");
                        $impresora->text("\n");
                        $impresora->text($auxTitleCat);
                        $impresora->text("\n");
                    }
                }
                $impresora->setEmphasis(false);
                ##product name
                $impresora->text("\n");
                $name = $key->item_name;
                $maxLength = 50;
                $shortname = substr($name, 0, $maxLength);
                $limit_first = 55;
                $first = str_pad($shortname, $limit_first);
                ##size -- 6 -- limit 10
                $sizename = $key->stock_physical;
                $sizename = number_format($sizename, 3);
                $maxLengthSize = 6;
                $shortsizename = substr($sizename, 0, $maxLengthSize);
                $limit_size = 8;
                $two = str_pad($shortsizename, $limit_size);
                
                // final text
                $final_text = $first.$two;
                $impresora->text($final_text);

            }

            $impresora->text("\n");
            $impresora->feed(2);
            $impresora->cut();
            $impresora->close();
            return response()->json(["message" => "IMPRESION DE TICKET DE VENTA"], 200 );
        } catch (\Throwable $th) {
            //throw $th;
            // Capturar mensaje del error
            $errorMessage = $th->getMessage();
            // Capturar el archivo donde ocurrió el error
            $errorFile = $th->getFile();
            // Capturar la línea donde ocurrió el error
            $errorLine = $th->getLine();
            $data = [
                "message" => "Error: {$errorMessage} en el archivo {$errorFile} en la línea {$errorLine}"
            ];
            Log::channel('stderr')->info("Error: {$errorMessage} en el archivo {$errorFile} en la línea {$errorLine}");
            return  $data;
        }
    }

    public function ticketMovimiento($movimiento,$store,$printer) {
        $movimiento = (object) $movimiento;
        $store = (object) $store;
        $nombreImpresora = "$printer";
        try {
            $connector = new WindowsPrintConnector($nombreImpresora);
            $impresora = new Printer($connector);
            
            $impresora->setJustification(Printer::JUSTIFY_CENTER);
            $impresora->text("$store->title\n");   
            $impresora->text("RUC: $store->nro_ruc\n");   
            $impresora->text("$store->razon_social\n");   
            $impresora->text("\n");   
            $impresora->setFont(PRINTER::FONT_B);
            $impresora->setJustification(Printer::JUSTIFY_LEFT);
            $date = $movimiento->transaction_at;
            $date_string = "FECHA DE MOVIMIENTO: ".$date;
            $impresora->text("$date_string\n");           
            $impresora->setJustification(Printer::JUSTIFY_LEFT);
            $impresora->text("\n");
            //-------------------------------------

            $name = $movimiento->category_trx_name;
            $maxLength = 45;
            $shortname = substr($name, 0, $maxLength);
            $limit_first = 48;
            $first = str_pad($shortname, $limit_first);

            $name2 = $movimiento->amount;
            $name2 = number_format($name2, 2);
            $maxLength = 8;
            $shortname = substr($name2, 0, $maxLength);
            $limit_first = 8;
            $two = str_pad($shortname, $limit_first);

            $saldo_inicial = $first.$two;
            $impresora->text("$saldo_inicial\n");   

            $impresora->text("\n");
            $impresora->cut();
            $impresora->close();

            //-------------------------------------
        } catch (\Throwable $th) {
            // Capturar mensaje del error
            $errorMessage = $th->getMessage();
            // Capturar el archivo donde ocurrió el error
            $errorFile = $th->getFile();
            // Capturar la línea donde ocurrió el error
            $errorLine = $th->getLine();
            $data = [
                "message" => "Error: {$errorMessage} en el archivo {$errorFile} en la línea {$errorLine}"
            ];
            Log::channel('stderr')->info("Error: {$errorMessage} en el archivo {$errorFile} en la línea {$errorLine}");
            return  $data;
            //throw $th;
        }
    }

    
    public static function CalculaEspacio($left, $right)  {
        $espaciosCentro = 64 - strlen($left) - strlen($right);
        return $espaciosCentro;
    }
}
