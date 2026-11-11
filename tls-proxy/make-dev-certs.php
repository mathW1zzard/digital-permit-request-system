<?php
// Creates a local certificate authority and a certificate for localhost and 127.0.0.1 in ../certs/.
// Development only: the private keys stay on this computer (certs/ is git-ignored).
// Run from the repository root: php tls-proxy/make-dev-certs.php
declare(strict_types=1);

$dir = __DIR__.'/../certs';
if (! is_dir($dir)) {
    mkdir($dir, 0700, true);
}
if (is_file("$dir/dev-ca.pem")) {
    exit("certs/dev-ca.pem already exists. Delete the certs folder first to make new ones.\n");
}

$cnf = "$dir/openssl.cnf";
file_put_contents($cnf, <<<'CNF'
[ req ]
distinguished_name = dn
prompt = no
[ dn ]
CN = placeholder
[ ca_ext ]
basicConstraints = critical, CA:TRUE, pathlen:0
keyUsage = critical, keyCertSign, cRLSign
subjectKeyIdentifier = hash
[ server_ext ]
basicConstraints = critical, CA:FALSE
keyUsage = critical, digitalSignature, keyEncipherment
extendedKeyUsage = serverAuth
subjectAltName = DNS:localhost, IP:127.0.0.1, IP:::1
CNF);
$opts = ['config' => $cnf, 'digest_alg' => 'sha256', 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];

$caKey = openssl_pkey_new($opts);
$caCsr = openssl_csr_new(['commonName' => 'Project 32 Dev CA (local only)'], $caKey, $opts);
$caCert = openssl_csr_sign($caCsr, null, $caKey, 825, $opts + ['x509_extensions' => 'ca_ext'], random_int(1, PHP_INT_MAX));

$key = openssl_pkey_new($opts);
$csr = openssl_csr_new(['commonName' => 'localhost'], $key, $opts);
$cert = openssl_csr_sign($csr, $caCert, $caKey, 397, $opts + ['x509_extensions' => 'server_ext'], random_int(1, PHP_INT_MAX));

openssl_x509_export_to_file($caCert, "$dir/dev-ca.pem");
openssl_x509_export_to_file($cert, "$dir/localhost.pem");
openssl_pkey_export_to_file($key, "$dir/localhost-key.pem", null, $opts);
unlink($cnf);
// The CA private key is not saved: no further certificates can be signed with it.

echo "Created certs/dev-ca.pem, certs/localhost.pem and certs/localhost-key.pem\n";
