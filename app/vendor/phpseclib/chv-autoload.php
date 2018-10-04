<?php
$mapping = [
	// Crypt
	'phpseclib\Crypt\AES',
	'phpseclib\Crypt\Base',
	'phpseclib\Crypt\Blowfish',
	'phpseclib\Crypt\DES',
	'phpseclib\Crypt\Hash',
	'phpseclib\Crypt\Random',
	'phpseclib\Crypt\RC2',
	'phpseclib\Crypt\RC4',
	'phpseclib\Crypt\Rijndael',
	'phpseclib\Crypt\RSA',
	'phpseclib\Crypt\TripleDES',
	'phpseclib\Crypt\Twofish',
	// File
	'phpseclib\Crypt\ANSI',
	'phpseclib\Crypt\ASN1',
	'phpseclib\Crypt\ASN1\Element',
	'phpseclib\Crypt\X509',
	// Math
	'phpseclib\Math\BigInteger',
	// Net
	'phpseclib\Net\SCP',
	'phpseclib\Net\SFTP',
	'phpseclib\Net\SFTP\Stream',
	'phpseclib\Net\SSH1',
	'phpseclib\Net\SSH2',
	// System
	'phpseclib\System\SSH',
];
spl_autoload_register(function ($class) use ($mapping) {
    if (in_array($class, $mapping)) {
        require dirname(__DIR__) . '/' . str_replace('\\', '/', $class) . '.php';
    }
}, true);