<?php
/*
 PSI_Crypt тоже унаследуется из прошлой версии, в конфиге будет только соль устанавливаться и метод криптовки.
 */
/*
 * Различные функции и методы шифрования
 * В алгоритмы я даже вдаваться не буду. Все взято из сети.
 * @author Kholstinnikov Grigoriy
 */
class PSI_Crypt extends PSI_Core {

    static public $config = array('salt'=>'', 'method'=>'md5');
    /**
     * сгенерировать пароль plain (без шифровки)
     * @param string $input
     */
    static public function plain($input) {
        return $input;
    }

    /**
     * сгенерировать пароль с учетом системной соли и метода шифрования
     * @param $input
     * @return mixed
     */
    static public function passwd($input) {
        list ($salt, $method) = array(static::$config['salt'], static::$config['method']);
        return self::$method($salt . $input);
    }
    /**
     * сгенерировать пароль для smb
     * @param string $input
     */
    static public function smb($input) {
        return self::lm_hash($input).":".self::nt_hash($input);
    }
    /**
     * сгенерировать пароль для shadow
     * @param string $input
     */
    static public function shadow($input) {
        return crypt($input, self::rnd_salt());
    }
    /**
     * сгенерировать пароль для htpasswd
     * @param string $input
     */
    static public function htpasswd($input) {
        return crypt($input, substr($input, 0, 2));
    }
    /**
     * сгенерировать пароль классический md5
     * @param string $input
     */
    static public function md5($input) {
        return md5($input);
    }
    /**
     * сгенерировать пароль классический md5
     * @param string $input
     */
    static public function short_md5($input) {
        return substr(md5($input), 0, 8);
    }
    /**
     * сгенерировать случайную salt для пароля
     * @param string $psString
     */
    static private function rnd_salt() {
        $start = rand(0,23);
        $md5 = uniqid(md5(rand(4000,8000).rand(40000,80000).rand(400000,800000).time()));
        return '$1$'.substr($md5, $start,$start+8 ).'$';
    }
    /**
     * сгенерировать NTHash пароля
     * @param string $input
     */
    static private function nt_hash($input) {
        $input=iconv('UTF-8','UTF-16LE',$input);
        $MD4Hash=hash('md4',$input);
        $NTLMHash=strtoupper($MD4Hash);
        return ($NTLMHash);
    }
    /**
     * сгенерировать LMHash пароля
     * @param string $input
     */
    static private function lm_hash($input) {
        $input = strtoupper(substr($input,0,14));

        $p1 = self::lm_hash_desencrypt(substr($input, 0, 7));
        $p2 = self::lm_hash_desencrypt(substr($input, 7, 7));

        return strtoupper($p1.$p2);
    }
    /**
     * сгенерировать LMHash_desencrypt пароля
     * @param string $input
     */
    static private function lm_hash_desencrypt ($input) {
        $key = array();
        $tmp = array();
        $len = strlen($input);

        for ($i=0; $i<7; ++$i) {
            $tmp[] = $i < $len ? ord($input[$i]) : 0;
        }

        $key[] = $tmp[0] & 254;
        $key[] = ($tmp[0] << 7) | ($tmp[1] >> 1);
        $key[] = ($tmp[1] << 6) | ($tmp[2] >> 2);
        $key[] = ($tmp[2] << 5) | ($tmp[3] >> 3);
        $key[] = ($tmp[3] << 4) | ($tmp[4] >> 4);
        $key[] = ($tmp[4] << 3) | ($tmp[5] >> 5);
        $key[] = ($tmp[5] << 2) | ($tmp[6] >> 6);
        $key[] = $tmp[6] << 1;

        $is = mcrypt_get_iv_size(MCRYPT_DES, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($is, MCRYPT_RAND);
        $key0 = "";

        foreach ($key as $k) {
            $key0 .= chr($k);
        }
        $crypt = mcrypt_encrypt(MCRYPT_DES, $key0, "KGS!@#$%", MCRYPT_MODE_ECB, $iv);

        return bin2hex($crypt);
    }
}

return function($salt=null, $method=null) {
    if ($salt) { PSI_Crypt::$config['salt'] = $salt; }
    if ($method) { PSI_Crypt::$config['method'] = $method; }
    return null;
}

?>