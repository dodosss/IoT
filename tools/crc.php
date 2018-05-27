<?php
set_time_limit(0);    


// php -S localhost:8000


// 重新建帧数据
if(isset($_POST['device_sn']) && isset($_POST['hex_str'])){
    $deviceSNStr    = $_POST['device_sn'];
    $hexStr         = $_POST['hex_str'];
    $deviceSNStr    = str_replace(" ", "", $deviceSNStr);
    $hexStr         = str_replace(array(" ", "&nbsp;"),  array("", ""), $hexStr);
    // check deviceSNStr
    if ($deviceSNStr == "" || $deviceSNStr== "\n" || strlen($deviceSNStr) !=16) {
            json_error(array("msg" => "设备号长度非16"));
    }
    try {
        $packageUtils = new PackageUtils();
        $package = $packageUtils->reBuild($deviceSNStr, $hexStr); // 格式校验，错误抛出异常 
        $data = array(    
            "device_sn_hex"  => $packageUtils->hexScreen($package->getDeviceSN()),        
            "hex_str_screen" => $packageUtils->hexScreen($package->toString()),
            "hex_str_screen2" => $packageUtils->hexScreen($package->toString(), "2"),
        );
        json_success($data);
    } catch (Exception $e) {
        json_error(array("msg" => $e->getMessage()));
    }    
}

function parse($hexStr) 
{
    $hexStr = str_replace(" ", "", $hexStr);
    if ($hexStr == "" || $hexStr== "\n") {
        echo "请输入要计算的16进制字符串<br/>";
        return;
    }
    try {
        $packageUtils = new PackageUtils();
        $package = $packageUtils->parse($hexStr);
        $html = '';
        $html .= '<div class="package">';
        $html .= '<div><span id="hex_str_screen">'.$packageUtils->hexScreen($package->toString()).'</span></div>';
        $html .= '<div><span id="hex_str_screen2">'.$packageUtils->hexScreen($package->toString(), "2").'</span></div>';        
        $html .= '<div><span id="device_sn_txt">'.$packageUtils->hexScreen($package->getDeviceSN()).'</span><span class="arrow">--></span>';
        $html .= '<input type="text" class="form-control input-sm" id="device_sn" name="device_sn" value="'.hexToStr($package->getDeviceSN()).'" maxlength="16" /> <span id="device_sn_msg"></span></div>';
        $html .= '<div>'.$package->getVersion()."</div>";
        $html .= '<div>'.$package->getConnectType()."</div>";
        $html .= '<div>'.$package->getCommand()."</div>";
        $html .= '<div>'.$package->getDataLength().'<span class="arrow">--></span>';
        $html .= '<span>'.$package->getDataLengthDec()."</span></div>";
        $html .= '<div>'.$package->getData().'</div>';
        $html .= '<div>'.$package->getSeq().'</div>';
        $html .= '<div>'.$package->getSignature().'</div>';
        $html .= '<div>'.$package->getEof().'</div>';
        $html .= '</div>';      
    } catch (Exception $e) {
        print("Caught exception: " . $e->getMessage());
    }

    echo $html;
}

function calc($hexStr) 
{
	$hexStr = str_replace(" ", "", $hexStr);
	if ($hexStr == "" || $hexStr== "\n") {
		echo "请输入要计算的16进制字符串<br/>";
		return;
	}
	if (strlen($hexStr)%2!=0) {
		echo "16进制字符串长度不能为单数<br/>";
		return;
	}
	if (!isHexString($hexStr)) {
		echo "非16进制字符串<br/>";
		return;
	}
	$packageUtils = new PackageUtils();
	echo $packageUtils->hexScreen($hexStr)."<br/>";
	$crc = new CRC16();
	$crcResult = $crc->calculationResult($hexStr);
	$crcResultCheck = $crcResult[2].$crcResult[3].$crcResult[0].$crcResult[1];
	$signature = $crcResultCheck;
	echo $packageUtils->hexScreen($signature)."<br/>";
}

// 功能函数
function json_success($data){
    json("1", $data);
}

function json_error($data){
    json("0", $data);
}

function json($status, $data){
    $result = array(
        "status" => $status,
        "data" => $data
    );
    echo json_encode($result);
    exit;
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

// package
class PackageUtils{
    private $crc;

    public function __construct()
    {
        $this->crc = new CRC16();
    }

    public function check($hexStr)
    {
        if ($hexStr == "" || $hexStr== "\n" || strlen($hexStr) < 40) {
            throw new \Exception("字符为空或长度不足20");
        }
        if (strlen($hexStr)%2!=0) {
            throw new \Exception("16进制字符串长度不能为单数");
        }
        if (!isHexString($hexStr)) {
            throw new \Exception("非16进制字符串");
        }
        $hexStr = str_replace(" ", "", $hexStr);
        $dataLength = substr($hexStr, 38, 2); // 40
        $signature = substr($hexStr, strlen($hexStr)-6, 4);
        $dataLengthDec = hexdec($dataLength);
        if ((54+($dataLengthDec*2))!=strlen($hexStr)) {
            throw new \Exception("数据长度错误");
        }
        $fixHexStr = substr($hexStr, 0, (strlen($hexStr)-2*3)); // 40        
        $crcResult = $this->crc->calculationResult($fixHexStr);
        $crcResultCheck = $crcResult[2].$crcResult[3].$crcResult[0].$crcResult[1];
        if(strtolower($signature)!=strtolower($crcResultCheck)){
            throw new \Exception("CRC16校验不通过");
        }
        return true;
    }    

    public function parse($hexStr)
    {
        $hexStr = str_replace(" ", "", $hexStr);
        $result = null;
        try {
            // 数据检测
            $this->check($hexStr);
            // 解析数据
            $deviceSN = substr($hexStr, 0, 32);
            $version = substr($hexStr, 32, 2);
            $connectType = substr($hexStr, 34, 2);
            $command = substr($hexStr, 36, 2);
            $dataLength = substr($hexStr, 38, 2);
            $dataLengthDec = hexdec($dataLength);
            $data = substr($hexStr, 40, $dataLengthDec*2);
            $seq = substr($hexStr, 40 + $dataLengthDec*2, 8);
            $signature = substr($hexStr, 48 + $dataLengthDec*2, 4);
            $eof = substr($hexStr, 52 + $dataLengthDec*2, 2);
            $result = $this->build($deviceSN, $version, $connectType, $command, $dataLength, $data, $seq, $signature, $eof);
        } catch (Exception $e) {
            print("Caught exception: " . $e->getMessage());
        }
        return $result;
    }

    public function reBuild($deviceSnStr, $hexStr) 
    {
        $deviceSnStr = str_replace(" ", "", $deviceSnStr);
        $hexStr = str_replace(" ", "", $hexStr);
        $result = null;
        if (strlen($deviceSnStr)!=16) {
            throw new \Exception("设备号长度非16位");
        }
        try {
            // 解析数据
            $package = $this->parse($hexStr);   // 格式校验，错误抛出异常         
            $deviceSN = strToHex($deviceSnStr); // 替换设备号
            $version = $package->getVersion();
            $connectType = $package->getConnectType();
            $command = $package->getCommand();
            $dataLength = $package->getDataLength();
            $data = $package->getData();
            $seq = $package->getSeq();
            $eof = $package->getEof();
            // 计算crc16 
            $hexStrNew = $deviceSN.$version.$connectType.$command.$dataLength.$data.$seq;
            $crcResult = $this->crc->calculationResult($hexStrNew);
            $crcResultCheck = $crcResult[2].$crcResult[3].$crcResult[0].$crcResult[1];
            $signature = $crcResultCheck; // 新signature
            $result = $this->build($deviceSN, $version, $connectType, $command, $dataLength, $data, $seq, $signature, $eof);
        } catch (Exception $e) {
            print("Caught exception: " . $e->getMessage());
        }
        return $result;
    }  

    public function hexScreen($hexStr, $type="1") {
        $hexStrScreen = "";
        try {
            // 临时数据
            $str = "";
            $hexStrArr = array();
            for($i=0, $j=1; $i<strlen($hexStr); $i = $i + 2, $j++){
                $hexStrArr[] = $hexStr[$i].$hexStr[$i+1];                
                $str .= '<span class="letter '.$this->getPackageScreenType($hexStr, $j).'">'.$hexStr[$i].$hexStr[$i+1].'</span>';
            }
            $hexStrScreen = $type=="1" ? implode("&nbsp;&nbsp;", $hexStrArr) : $str;                     
        } catch (Exception $e) {
            print("Caught exception: " . $e->getMessage());
        }
        return $hexStrScreen;
    }

    private function getPackageScreenType($hexStr, $pos) {
            $class = "";
            if (strlen($hexStr)>=40) {
                $dataLength = substr($hexStr, 38, 2);
                $dataLengthDec = hexdec($dataLength);                
                if($pos>=0 && $pos<17){
                    $class = "sec_1";
                }else if($pos==17){
                    $class = "sec_2";//1
                }else if($pos==18){
                    $class = "sec_3";//2
                }else if($pos==19){
                    $class = "sec_4";//3
                }else if($pos==20){
                    $class = "sec_5";// 4 dataLength
                }else if($pos>20 && $pos<(21 + $dataLengthDec)){
                    $class = "sec_6";
                }else if($pos>(20 + $dataLengthDec) && $pos<(25 + $dataLengthDec)){
                    $class = "sec_7";
                }else if($pos>(24 + $dataLengthDec) && $pos<(27 + $dataLengthDec)){
                    $class = "sec_8";
                }else if($pos>(26 + $dataLengthDec) && $pos<(28 + $dataLengthDec)){
                    $class = "sec_9";
                }
            }
            return $class;
    }

    private function build($deviceSN, $version, $connectType, $command, $dataLength, $data, $seq, $signature, $eof)
    {
        $p = new Package($deviceSN, $version, $connectType, $command, $dataLength, $data, $seq, $signature, $eof);
        return $p;
    }
}


// package
class Package{
    private $deviceSN;
    private $version;
    private $connectType;
    private $command;
    private $dataLength;
    private $data;
    private $seq;
    private $signature;
    private $eof;

    public function __construct($deviceSN, $version, $connectType, $command, $dataLength, $data, $seq, $signature, $eof)
    {
        $this->deviceSN = $deviceSN;
        $this->version = $version;
        $this->connectType = $connectType;
        $this->command = $command;
        $this->dataLength = $dataLength;
        $this->data = $data;
        $this->seq = $seq;
        $this->signature = $signature;
        $this->eof = $eof;
        return $this;
    }

    public function getDeviceSN()
    {
        return $this->deviceSN;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function getConnectType()
    {
        return $this->connectType;
    }

    public function getCommand()
    {
        return $this->command;
    }

    public function getDataLength()
    {
        return $this->dataLength;
    }

    public function getDataLengthDec()
    {
        return hexdec($this->dataLength);
    }

    public function getData()
    {
        return $this->data;
    }

    public function getSeq()
    {
        return $this->seq;
    }

    public function getSignature()
    {
        return $this->signature;
    }

    public function getEof()
    {
        return $this->eof;
    }

    public function toString()
    {
        return $this->deviceSN.$this->version.$this->connectType.$this->command.$this->dataLength.$this->data.$this->seq.$this->signature.$this->eof;
    }
}

// crc16工具类
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
                                    $('#hex_str_screen2').html(result.data.hex_str_screen2);

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
        #hex_str, #hex_str2{
            width: 100%;
            display: inline-block;
        }
        .decode, .calc{
            background-color: #fff;
        } 
        .title{
            font-weight: bold;
        }   
        .desc{
            display: inline-block;
            height: 28px;
            line-height: 28px;
            color: #ddd;
        }
        .arrow{
            color: #ddd;
            padding-right: 5px;
            padding-left: 5px;
        }
        #device_sn{
            width: 250px;
            display: inline-block;
            font-size: 16px;
            margin-top:5px;
            font-weight: bold;
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

        .decode{
            clear: both;;
        }

        .package div{
            display: block;
            clear: both;
        }
        
        .letter{
             margin-top:5px;
             width: 30px;
             height: 28px;
             line-height: 28px;
             float: left;
             text-align: center;
             border-top:  1px solid #ebe9e9;    
             border-left:  1px solid #ebe9e9;    
             border-bottom: 1px solid #ebe9e9;    
        }

        .sec_1{
            background: #f9be8f;
        }

        .sec_2{
            background: #92cddc;
        }

        .sec_3{
            background: #b1a0c6;
        }

        .sec_4{
            background: #c2d59b;
        }

        .sec_5{
            background: #d99493;
        }

        .sec_6{
            background: #94b3d6;
        }

        .sec_7{
            background: #928852;
        }

        .sec_8{
            background: #00af50;
        }

        .sec_9{
            color: #fff;
            background: #3e3e3e;
        }

        .howto{
            margin-bottom: 8px;
        }

        .howto div{
            display: inline-block;
            height: 28px;
            line-height: 28px;            
            margin-right: 15px;
        }

        .howto div .letter{
            margin-top:0px;
        }
        .howto div .desc{
            border:  1px solid #ebe9e9;
            padding-right: 3px;
            color: #9d9a9a;
            width: 65px;
        }
    </style>
</head>
<body>

<div>    
    <div class="howto">
        <div><span class="sec_1 letter">01</span><span class="desc">设备类型</span></div>
        <div><span class="sec_2 letter">01</span><span class="desc">软件版本</span></div>
        <div><span class="sec_3 letter">01</span><span class="desc">通讯类型</span></div>
        <div><span class="sec_4 letter">01</span><span class="desc">指令名称</span></div>
        <div><span class="sec_5 letter">01</span><span class="desc">数据长度</span></div>
        <div><span class="sec_6 letter">01</span><span class="desc">数据域</span></div>
        <div><span class="sec_7 letter">01</span><span class="desc">seq</span></div>
        <div><span class="sec_8 letter">01</span><span class="desc">crc</span></div>
        <div><span class="sec_9 letter">01</span><span class="desc">结束符</span></div>
    </div>
    <div class="text-left decode">
        <span class="title">Hex Decode</span>
        <span class="desc">（数据解码）</span><br/>
        <form action="?" method="get" class="form-inline text-left">
            <textarea id="hex_str" name="hex_str" class="form-control" style="height:55px;" placeholder="16进制字符串"><?php echo ( (isset($_GET['hex_str']) && $_GET['hex_str'] ) ? $_GET['hex_str'] : "303830353236313046454243303030310000020c0102010202020202020202020000002733560a");?></textarea>
            <input type="submit" value="解码" class="btn btn-default input-sm btn_submit" />
        </form>
    </div>
    <hr/>
    <div class="text-left calc">
        <span class="title">CRC16 CALC</span>
        <span class="desc">（CRC16值计算）</span><br/>
        <form action="?" method="get" class="form-inline text-left">
            <textarea id="hex_str2" name="hex_str2" class="form-control" style="height:55px;" placeholder="16进制字符串"><?php echo (isset($_GET['hex_str2']) ? $_GET['hex_str2'] : "");?></textarea>
            <input type="submit" value="计算" class="btn btn-default input-sm btn_submit" />
        </form>
    </div>
    <?php
        if( isset($_GET['hex_str']) ){
            $hexStr = $_GET['hex_str'];
            parse($hexStr);
        }

        if( isset($_GET['hex_str2']) ){
            $hexStr = $_GET['hex_str2'];
            calc($hexStr);
        }
    ?>
</div>
</body>
</html>