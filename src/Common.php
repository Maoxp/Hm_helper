<?php

namespace common\library;

use Yii;

class Common {
    /**
     * 短信发送
     * @param int $mobile
     * @param int $tplId
     * @param null $params
     * @return bool
     * @author mxp
     *  2018-7-18
     */
    public function sendMsg($key, $mobile, $tplId, $params = null) {
        header('content-type:text/html;charset=utf-8');
        $sendUrl = 'http://v.juhe.cn/sms/send'; //短信接口的URL

        $paramStr = "";
        if (is_array($params) && !empty($params)) {
            foreach ($params as $k => $v) {
                $paramStr .= $paramStr == '' ? '#' . $k . '#=' . $v : '&#' . $k . '#=' . $v;
            }
        }

        $smsConf = array(
            'key' => $key, //您申请的APPKEY
            'mobile' => $mobile,  //接受短信的用户手机号码
            'tpl_id' => $tplId,   //您申请的短信模板ID，根据实际情况修改
            'tpl_value' => $paramStr //您设置的模板变量，根据实际情况修改
        );
        $content = self::curl_method($sendUrl, $smsConf, 'post'); //请求发送短信
        // $resMsg = '';
        $isSuccess = false;
        if ($content) {
            $result = json_decode($content, true);
            $error_code = $result['error_code'];
            if ($error_code == 0) {
                //状态为0，说明短信发送成功
                $isSuccess = true;
                //       $resMsg = "短信发送成功,短信ID：".$result['result']['sid'];
            } else {
                //状态非0，说明失败
                $msg = $result['reason'];
                //     $resMsg = "短信发送失败(".$error_code.")：".$msg;
            }
        } else {
            //返回内容异常，以下可根据业务逻辑自行修改
            //$resMsg = "请求发送短信失败";
        }
        return $isSuccess;
    }

    /**
     * HTTP请求
     * @param $url
     * @param mixed $params
     * @param string $method
     * @param  array $headers
     * @return bool|mixed
     * @author  mxp
     * 2018-7-18
     */
    public static function curl_method($url, $params = "", $method = 'get', $headers = []) {
        //对空格进行转义
        $url = str_replace(' ', '+', $url);
        $httpInfo = array();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.22 (KHTML, like Gecko) Chrome/25.0.1364.172 Safari/537.22');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);    // 对认证证书来源的检查
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);    // 从证书中检查SSL加密算法是否存在

        $requestMethod = strtolower($method);
        switch ($requestMethod) {
            case 'post':
                curl_setopt($ch, CURLOPT_POST, TRUE);
                if (!empty($params)) {
                    if (is_array($params)) {
                        //todo 如果参数是多维的 使用 http_build_query()处理
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                    } elseif(is_string($params)) {
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $params); // Don't know why: if not set,  413 Request Entity Too Large
                    }
                }

                break;
            case 'get':
                if ($params) {
                    $sep = false === strpos($url, '?') ? '?' : '&';
                    $url .= $sep . http_build_query($params);
                }
                break;
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        if (!empty($headers)) {
            curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);    //设置header
            //'Content-Type: application/json; charset=utf-8'  发送Json对象数据
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        if ($response === FALSE) {
            return false;
        }
        curl_close($ch);
        return $response;
    }

    /**
     * 地址转换经纬度
     * @param string $address
     * @return mixed
     * @author mxp
     *  2018-7-18
     */
    public static function Location_to_Convert($address) {
        $ulr = 'http://apis.map.qq.com/ws/geocoder/v1/';
        $param = [
            'address' => $address,
            'key' => Yii::$app->params['Map']['key']
        ];
        return self::curl_method($ulr, $param);
    }

    /**
     * 将xml转换为数组
     * @param string $xml  需要转化的xml
     * @return mixed
     * @author mxp
     *  2018-7-18
     */
    public static function xml_to_array($xml) {
        $ob = simplexml_load_string($xml);
        $json = json_encode($ob);
        $array = json_decode($json, true);
        return $array;
    }

    /**
     * 将数组转化成xml
     * @param array $data 需要转化的数组
     * @return string
     * @author mxp
     * 2018-7-18
     */
    public static function data_to_xml($data) {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        $xml = '';
        foreach ($data as $key => $val) {
            if (is_null($val)) {
                $xml .= "<$key/>\n";
            } else {
                if (!is_numeric($key)) {
                    $xml .= "<$key>";
                }
                $xml .= (is_array($val) || is_object($val)) ? self::data_to_xml($val) : $val;
                if (!is_numeric($key)) {
                    $xml .= "</$key>";
                }
            }
        }
        return $xml;
    }

    /**
     * 接收xml数据并转化成数组
     * @return array
     * @author mxp
     * 2018-7-18
     */
    public static function getRequestBean() {
        $bean = simplexml_load_string(file_get_contents('php://input')); // simplexml_load_string() 函数把 XML 字符串载入对象中。如果失败，则返回 false。
        $request = array();
        foreach ($bean as $key => $value) {
            $request[(string)$key] = (string)$value;
        }
        return $request;
    }

    /**
     * 日志方法
     * @param string $log
     * @param mixed $logName
     * @author mxp
     * 2018-7-18
     */
    public static function writeLog($log, $logName = "") {
        $dir = Yii::$app->getRuntimePath() . DIRECTORY_SEPARATOR. "logs";
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        if (empty($logName)) {
            $logName = date("Y-m-d");
        }
        $filename = $dir . DIRECTORY_SEPARATOR . $logName . ".log";
        file_put_contents($filename, date("Y-m-d H:i:s") . "\t" . $log . PHP_EOL, FILE_APPEND);
    }

    /**
     *  字节数Byte转换为KB、MB、GB、TB
     * @param int $num  字节长度 strlen()函数统计
     * @return string
     * @author  mxp
     * 2018-7-16 10:22
     */
    public static function getFileSize($num) {
        $p = 0;
        $format='bytes';
        if($num>0 && $num<1024){
            $p = 0;
            return number_format($num).' '.$format;
        }
        if($num>=1024 && $num<pow(1024, 2)){
            $p = 1;
            $format = 'KB';
        }
        if ($num>=pow(1024, 2) && $num<pow(1024, 3)) {
            $p = 2;
            $format = 'MB';
        }
        if ($num>=pow(1024, 3) && $num<pow(1024, 4)) {
            $p = 3;
            $format = 'GB';
        }
        if ($num>=pow(1024, 4) && $num<pow(1024, 5)) {
            $p = 3;
            $format = 'TB';
        }
        $num /= pow(1024, $p);
        return number_format($num, 3).' '.$format;
    }

    /**
     * 版本比对
     * @param float $version1 版本号1
     * @param float $version2 版本号2
     * @return mixed
     * @author  mxp
     *  2018-7-18 8:11
     */
    public static function ver_compare($version1, $version2) {
        $version1 = str_replace('.', '', $version1);
        $version2 = str_replace('.', '', $version2);
        $oldLength = mb_strlen($version1);
        $newLength = mb_strlen($version2);
        if (is_numeric($version1) && is_numeric($version2)) {
            if ($oldLength > $newLength) {
                $version2 .= str_repeat('0', $oldLength - $newLength);
            }
            if ($newLength > $oldLength) {
                $version1 .= str_repeat('0', $newLength - $oldLength);
            }
            $version1 = intval($version1);
            $version2 = intval($version2);
        }
        return version_compare($version1, $version2);
    }

    /**
     * 生成随机验证码图片
     * @param string $randomString 随机字符串
     * @return string
     * @author  mxp
     * 2018-7-18 8:14
     */
    public static function generatorImg($randomString) {
        $img_height = 70;         //先定义图片的长、宽
        $img_width = 25;
        // $randomString = generatorString(4);     //生产验证码字符

        $resourceImg = imagecreate($img_height, $img_width);    //生成图片
        imagecolorallocate($resourceImg, 255, 255, 255);            //图片底色，ImageColorAllocate第1次定义颜色PHP就认为是底色了
        $black = imagecolorallocate($resourceImg, 0, 0, 0);        //定义需要的黑色

        for ($i = 1; $i <= 100; $i++) {
            imagestring($resourceImg, 1, mt_rand(1, $img_height), mt_rand(1, $img_width), "@", imagecolorallocate($resourceImg, mt_rand(200, 255), mt_rand(200, 255), mt_rand(200, 255)));
        }

        //为了区别于背景，这里的颜色不超过200，上面的不小于200
        for ($i = 0; $i < strlen($randomString); $i++) {
            imagestring($resourceImg, mt_rand(5, 6), $i * $img_height / 4 + mt_rand(2, 3), mt_rand(1, $img_width / 2 - 2), $randomString[$i], imagecolorallocate($resourceImg, mt_rand(0, 100), mt_rand(0, 150), mt_rand(0, 200)));
        }
        imagerectangle($resourceImg, 0, 0, $img_height - 1, $img_width - 1, $black);//画一个矩形
        ob_start();
        ImagePNG($resourceImg);                    //生成png格式
        $data = ob_get_contents();
        ob_end_clean();
        ImageDestroy($resourceImg);

        return $data;
    }

    /**
     *  随机字符串
     * @param  int $length 长度
     * @return string
     * @author  mxp
     *  2018-7-18
     */
    public static function generatorString($length = 4) {
        $char = "0,1,2,3,4,5,6,7,8,9,A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z";
        $list = explode(",", $char);
        $randomString = "";
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $list[rand(0, 35)];
        }
        return $randomString;
    }
}