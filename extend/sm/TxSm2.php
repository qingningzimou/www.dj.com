<?php

namespace sm;
use think\facade\Env;
class TxSm2
{
    protected static $keyName = '30f733b28015ed51c13e0e9f2ecc9889';

    /**
     * 解密
     * @return string
     */
    public function privateDecrypt($data)
    {
        return (openssl_private_decrypt(base64_decode($data), $decrypted, self::getPrivateKey())) ? $decrypted : null;
    }

    /**
     * 加密
     * @return string|null
     */
    public function privateEncrypt($data)
    {
        return openssl_public_encrypt($data, $encrypted, self::getPublicKey()) ? base64_encode($encrypted) : null;
    }

    /**
     * 获取私钥
     * @return bool|resource
     */
    private static function getPrivateKey()
    {
        $private_path = root_path(). 'extend/sm/private_key_' . self::$keyName . '.pem';
        $content = file_get_contents($private_path);
        return openssl_pkey_get_private($content);
    }
    /**
     * 获取公钥
     * @return bool|resource
     */
    private static function getPublicKey()
    {
        $public_path = root_path(). 'extend/sm/public_key_' . self::$keyName . '.pem';
        $content = file_get_contents($public_path);
        return openssl_pkey_get_public($content);
    }
}