<?php
set_time_limit(0);    


if(isset($_POST['device_sn']) && isset($_POST['hex_str'])){
    //var_dump($_POST);
    $device_sn = $_POST['device_sn'];
    $hex_str = $_POST['hex_str'];
    $device_sn = str_replace(" ", "", $device_sn);
    $hex_str = str_replace(array(" ", "&nbsp;"),  array("", ""), $hex_str);
    // check device_sn
    if ($device_sn == "" || $device_sn== "\n" || strlen($device_sn) !=16) {
            $data = array(
                "status" => "0",
                "data" => array(
                    "msg" => "设备号长度非16"
                )
            );
            echo json_encode($data);
            exit;
    }
    // check hex_str
    if (!check($hex_str)) {
            $data = array(
                "status" => "0",
                "data" => array(
                    "msg" => "帧数据hex_str格式错误"
                )
            );
            echo json_encode($data);
            exit;
    }
    // check reBuildPackage
    $result = reBuildPackage($device_sn, $hex_str);
    if (!check($result)) {
            $data = array(
                "status" => "0",
                "data" => array(
                    "msg" => "转换失败"
                )
            );
            echo json_encode($data);
            exit;
    }
    $device_sn_hex  = hexScreen(strToHex($device_sn));
    $hex_str_screen = hexScreen($result);
    $data = array(
        "status" => "1",
        "data" => array(            
            "hex_str_screen" => $hex_str_screen,
            "device_sn_hex"  => $device_sn_hex
        )
    );
    echo json_encode($data);
    exit;
}


class CRC16
{
    private $_calculate_type;
    private $_calculate_type_hash = [
        'IBM',
        'MAXIM',
        'USB',
        'MODBUS',
        'CCITT',
        'CCITT-FALSE',
        'X25',
        'XMODEM',
        'DNP'
    ];

    /**
     * @param string $calculate
     */
    public function __construct($calculate = 'MODBUS')
    {
        $this->_calculate_type = in_array(strtoupper($calculate), $this->_calculate_type_hash) ? strtoupper($calculate) : 'MODBUS';
    }

    /**
     * @param $str
     * @return null|string
     */
    public function calculationResult($str)
    {
        $result = null;
        switch ($this->_calculate_type) {
            case 'MODBUS':
                $result = $this->crc16Modbus($str);
                break;
        }
        return $result;
    }

    /**
     * crc16 for Modbus
     * @param $str
     * @return string
     */
    private function crc16Modbus($str)
    {
        $data = pack('H*', $str);
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]);
            for ($j = 8; $j != 0; $j--) {
                if (($crc & 0x0001) != 0) {
                    $crc >>= 1;
                    $crc ^= 0xA001;
                } else $crc >>= 1;
            }
        }
        return sprintf('%04X', $crc);
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="author" content="dodosss">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>crc16</title>
    <link rel="stylesheet" href="https://cdn.bootcss.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous" />
    <script src="https://cdn.bootcss.com/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://cdn.bootcss.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>

    <script>
        /**
         * crc
         *
         * @Author dodosss
         * @Date 2018/05/21
         */
        "use strict"        

        $(function() {  

            $("#device_sn").on('input',function(e){
                var device_sn = $("input[name='device_sn']").val();
                var hex_str   = $("#hex_str_screen").html();                
                device_sn = device_sn.replace(/[^\u4e00-\u9fa5a-zA-Z0-9\w]/g,'');
                hex_str = hex_str.replace(/&nbsp;/ig, "");
                if(device_sn.length!=16){
                    $('#device_sn_msg').html('<span class="tip-error">设备号长度非16位</span>');
                    return;
                }
                $('#device_sn_msg').html("");
                console.log(hex_str);
                $.ajax({
                    type: "POST",
                    url: "?",
                    data: {"device_sn":device_sn, "hex_str":hex_str},
                    dataType: "json",
                    success: function(result){
                                console.log(result);
                                if(result.status=="1"){
                                    $('#device_sn_txt').html(result.data.device_sn_hex);
                                    $('#hex_str_screen').html(result.data.hex_str_screen);
                                    $('#device_sn_msg').html('<span class="tip-success">转换成功</span>');
                                } else if(result.status=="0"){
                                    $('#device_sn_msg').html('<span class="tip-error">' + result.data.msg + '</span>');
                                }
                                
                    }
                });
            });
        });
    </script>
    <style>
        body{
            font-family: "微軟正黑體", "Century Gothic", sans-serif, serif;
            margin:25px auto;
            margin-top:0;
            width:60%;
        }
        hr {
            margin-top: 5px;
            margin-bottom: 5px;
        }
        #hex_str, #hex_str2, #hex_str3{
            width: 100%;
            display: inline-block;
        }
        .decode, .calc, .convert{
            background-color: #fff;
        } 
        .title{
            font-weight: bold;
        }   
        .code{
            color: #ddd;
        } 

        #device_sn{
            width: 200px;
            display: inline-block;
        } 

        .tip-success:before, .tip-error:before {
            font-family:FontAwesome;
            font-style:normal;
            font-weight:400;
            speak:none;
            display:inline-block;
            text-decoration:inherit;
            width:1em;
            margin-right:.2em;
            text-align:center;
            font-variant:normal;
            text-transform:none;
            line-height:1em;
            margin-left:.2em;
            -webkit-font-smoothing:antialiased;
            -moz-osx-font-smoothing:grayscale
        }
        .tip-error:before {
            /*content:'f057';*/
        }
        .tip-success:before {
            /*content:'f00c';*/
        }
        .tip-error {
            color: #D8000C;
            background-color: #FFD2D2;
        } 
        .tip-success {
            color: #4F8A10;
            background-color: #DFF2BF;
        }
    </style>
</head>
<body>

<div>    
    <div class="text-left decode">
        <span class="title">Hex Decode</span>
        <span class="code">（数据解码）</span><br/>
        <form action="?" method="get" class="form-inline text-left">
            <textarea id="hex_str" name="hex_str" class="form-control" style="height:55px;" placeholder="16进制字符串，如30 32 30 31 30 31 30 30 41 41 41 41 30 30 30 31 00 00 00 12 10 10 00 00 01 11 30 12 00 00 01 11 60 13 00 00 00 11 00 00 00 01 1D 72 0A"><?php echo (isset($_GET['hex_str']) ? $_GET['hex_str'] : "");?></textarea>
            <input type="submit" value="解码" class="btn btn-default input-sm btn_submit" />
        </form>
    </div>
    <hr/>
    <div class="text-left calc">
        <span class="title">CRC16 CALC</span>
        <span class="code">（CRC16值计算）</span><br/>
        <form action="?" method="get" class="form-inline text-left">
            <textarea id="hex_str2" name="hex_str2" class="form-control" style="height:55px;" placeholder="16进制字符串，如30 32 30 31 30 31 30 30 41 41 41 41 30 30 30 31 00 00 00 12 10 10 00 00 01 11 30 12 00 00 01 11 60 13 00 00 00 11 00 00 00 01"><?php echo (isset($_GET['hex_str2']) ? $_GET['hex_str2'] : "");?></textarea>
            <input type="submit" value="计算" class="btn btn-default input-sm btn_submit" />
        </form>
    </div>
    <hr/>
    <div class="text-left convert">
        <span class="title">String To Hex</span>
        <span class="code">（字符串转16进制字符）</span><br/>
        <form action="?" method="get" class="form-inline text-left">
            <textarea id="hex_str3" name="hex_str3" class="form-control" style="height:55px;" placeholder="字符串，如02010100AAAA0001"><?php echo (isset($_GET['hex_str3']) ? $_GET['hex_str3'] : "");?></textarea>
            <input type="submit" value="转换" class="btn btn-default input-sm btn_submit" />
        </form>
    </div>
    <hr/>
    <?php
    
    function hexScreen($hexStr) {
        $hexStr = str_replace(" ", "", $hexStr);
        $hexStrScreen = "";
        if($hexStr){
            $hexStrArr = array();
            for($i=0; $i<strlen($hexStr); $i = $i + 2){
                $hexStrArr[] = $hexStr[$i].$hexStr[$i+1];
            }
            $hexStrScreen = implode("&nbsp;&nbsp;", $hexStrArr);
        }        
        return $hexStrScreen;
    }

    function hexToStr($hex){
        $str = "";
        for ($i=0; $i < strlen($hex)-1; $i+=2){
            $str .= chr(hexdec($hex[$i].$hex[$i+1]));
        }
        return $str;
    }

    function strToHex($str){
        $hex='';
        for ($i=0; $i < strlen($str); $i++){
            $hex .= dechex(ord($str[$i]));
        }
        return $hex;
    }

    function isHexString($str){
        return ctype_xdigit($str);
    }

    function check($hexStr) {
        if ($hexStr == "" || $hexStr== "\n" || strlen($hexStr) < 40) {
            echo "字符为空或长度不足20<br/>";
            return false;
        }
        if (strlen($hexStr)%2!=0) {
            echo "16进制字符串长度不能为单数<br/>";
            return false;
        }
        if (!isHexString($hexStr)) {
            echo "非16进制字符串<br/>";
            return false;
        }
        $hexStr = str_replace(" ", "", $hexStr);
        $datalength = substr($hexStr, 38, 2); // 40
        $signature = substr($hexStr, strlen($hexStr)-6, 4);
        $datalengthDec = hexdec($datalength);
        if ((54+($datalengthDec*2))!=strlen($hexStr)) {
            echo "数据长度错误<br/>";
            return false;
        }
        $fixHexStr = substr($hexStr, 0, (strlen($hexStr)-2*3)); // 40
        // 
        $crc = new CRC16();
        $crcResult = $crc->calculationResult($fixHexStr);
        $crcResultCheck = $crcResult[2].$crcResult[3].$crcResult[0].$crcResult[1];
        if(strtolower($signature)!=strtolower($crcResultCheck)){
            echo "CRC16校验不通过<br/>";
            return false;
        }
        return true;
    }

    function reBuildPackage($deviceSn, $hexStr) 
    {
        $deviceSn = str_replace(" ", "", $deviceSn);
        $hexStr = str_replace(" ", "", $hexStr);
        if (strlen($deviceSn)==16 && check($hexStr)) {
            // 替换设备号
            $deviceSn = strToHex($deviceSn);
            $version = substr($hexStr, 32, 2);
            $connecttype = substr($hexStr, 34, 2);
            $command = substr($hexStr, 36, 2);
            $datalength = substr($hexStr, 38, 2);
            $datalengthDec = hexdec($datalength);
            $data = substr($hexStr, 40, $datalengthDec*2);
            $seq = substr($hexStr, 40 + $datalengthDec*2, 8);
            $signature = substr($hexStr, 48 + $datalengthDec*2, 4);
            $eof = substr($hexStr, 52 + $datalengthDec*2, 2);
            // 计算crc16
            $crc = new CRC16();
            $hexStrNew = $deviceSn.$version.$connecttype.$command.$datalength.$data.$seq;
            $crcResult = $crc->calculationResult($hexStrNew);
            $crcResultCheck = $crcResult[2].$crcResult[3].$crcResult[0].$crcResult[1];
            $signature = $crcResultCheck;

            $hexStrNew = $hexStrNew.$signature.$eof;

            return $hexStrNew;
        }
        return "";
    }

    function parse($hexStr) 
    {
        //echo var_dump(check($hexStr));
        $hexStr = str_replace(" ", "", $hexStr);
        if (check($hexStr)) {            
            echo '<span id="hex_str_screen">'.hexScreen($hexStr).'</span> <br/>';
            $deviceSN = substr($hexStr, 0, 32);
            $version = substr($hexStr, 32, 2);
            $connecttype = substr($hexStr, 34, 2);
            $command = substr($hexStr, 36, 2);
            $datalength = substr($hexStr, 38, 2);
            $datalengthDec = hexdec($datalength);
            $data = substr($hexStr, 40, $datalengthDec*2);
            $seq = substr($hexStr, 40 + $datalengthDec*2, 8);
            $signature = substr($hexStr, 48 + $datalengthDec*2, 4);
            $eof = substr($hexStr, 52 + $datalengthDec*2, 2);

            //echo hexScreen($deviceSN)."-->";
            echo '<span id="device_sn_txt">'.hexScreen($deviceSN).'</span> -->';
            echo '<input type="text" class="form-control input-sm" id="device_sn" name="device_sn" value="'.hexToStr($deviceSN).'" maxlength="16" /> <span id="device_sn_msg"></span> <br/>';
            //echo hexToStr($deviceSN)."<br/>";           
            echo $version."<br/>";
            echo $connecttype."<br/>";
            echo $command."<br/>";
            echo $datalength."-->";
            echo $datalengthDec."<br/>";
            echo hexScreen($data)."<br/>";
            echo $seq."<br/>";
            echo $signature."<br/>";
            echo $eof."<br/>";
        }
    }



    function calc($hexStr) 
    {
        $hexStr = str_replace(" ", "", $hexStr);
        if ($hexStr == "" || $hexStr== "\n") {
            echo "请输入要计算的16进制字符串<br/>";
            return false;
        }
        if (strlen($hexStr)%2!=0) {
            echo "16进制字符串长度不能为单数<br/>";
            return false;
        }
        if (!isHexString($hexStr)) {
            echo "非16进制字符串<br/>";
            return false;
        }
        
        echo hexScreen($hexStr)."<br/>";
        $crc = new CRC16();
        $crcResult = $crc->calculationResult($hexStr);
        $crcResultCheck = $crcResult[2].$crcResult[3].$crcResult[0].$crcResult[1];
        $signature = $crcResultCheck;
        echo $signature."<br/>";
    }

    function convert($str) 
    {
        if ($str == "" || $str== "\n") {
            echo "请输入要转换的字符串<br/>";
            return false;
        }
        $str = str_replace(" ", "", $str);
        echo hexScreen(strToHex($str))."<br/>";
        echo strToHex($str)."<br/>";
    }

    
    if( isset($_GET['hex_str']) ){
        $hexStr = $_GET['hex_str'];
        parse($hexStr);
    }

    if( isset($_GET['hex_str2']) ){
        $hexStr = $_GET['hex_str2'];
        calc($hexStr);
    }
    if( isset($_GET['hex_str3']) ){
        $hexStr = $_GET['hex_str3'];
        convert($hexStr);
    }

    if( isset($_GET['hex_str4']) ){
        $hexStr = $_GET['hex_str4'];
        hexToDec($hexStr);
    }
    ?>
</div>
</body>
</html>