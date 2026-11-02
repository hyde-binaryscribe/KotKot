<?php
declare(strict_types=1);

/**
 * Web Push crypto checked against the example of RFC 8291 (appendix A),
 * and VAPID signatures checked with OpenSSL.
 *
 *   php tests/push_test.php
 */

putenv('KOTKOT_DATA=' . sys_get_temp_dir() . '/kotkot-push-' . bin2hex(random_bytes(4)));
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/push.php';

$failures = 0;
function check(string $what, bool $ok): void
{
    global $failures;
    echo ($ok ? '  ok  ' : '  FAIL ') . $what . "\n";
    $failures += $ok ? 0 : 1;
}

echo "Web Push\n";

// RFC 8291, appendix A
$plaintext = b64u_decode('V2hlbiBJIGdyb3cgdXAsIEkgd2FudCB0byBiZSBhIHdhdGVybWVsb24');
$asPublic = b64u_decode('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8');
$asPrivate = b64u_decode('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw');
$authSecret = b64u_decode('BTBZMqHH6r4Tts7J_aSIgg');
$uaPublic = b64u_decode('BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4');
$salt = b64u_decode('DGv6ra1nlYgDCS1FRnbzlw');
$expected = 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN';

$asKey = openssl_pkey_get_private(p256_private_pem($asPrivate));
check('private key from raw scalar gives the RFC public key', $asKey && p256_raw_public($asKey) === $asPublic);
$body = webpush_encrypt($plaintext, $uaPublic, $authSecret, $asKey, $salt);
check('encrypted message identical to RFC 8291', b64u_encode($body) === $expected);

// random keys: a browser-side decryption gets the text back
$ua = p256_new_key();
$uaRaw = p256_raw_public($ua);
$auth = random_bytes(16);
$msg = '{"title":"KotKot","body":"Pensez à fermer la porte"}';
$enc = webpush_encrypt($msg, $uaRaw, $auth);
$salt2 = substr($enc, 0, 16);
$keyLen = ord($enc[20]);
$asPub2 = substr($enc, 21, $keyLen);
$shared = openssl_pkey_derive(openssl_pkey_get_public(p256_public_pem($asPub2)), $ua, 32);
$prkKey = hash_hmac('sha256', $shared, $auth, true);
$ikm = hash_hmac('sha256', "WebPush: info\0" . $uaRaw . $asPub2 . "\x01", $prkKey, true);
$prk = hash_hmac('sha256', $ikm, $salt2, true);
$cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\0\x01", $prk, true), 0, 16);
$nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\0\x01", $prk, true), 0, 12);
$ct = substr($enc, 21 + $keyLen, -16);
$plain = openssl_decrypt($ct, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($enc, -16));
check('a browser can decrypt it', $plain === $msg . "\x02");

// VAPID
$header = vapid_header('https://fcm.googleapis.com/fcm/send/abc');
preg_match('/^vapid t=([^,]+), k=(.+)$/', $header, $m);
[$h64, $p64, $s64] = explode('.', $m[1]);
$claims = json_decode(b64u_decode($p64), true);
check('VAPID audience is the push service', $claims['aud'] === 'https://fcm.googleapis.com');
$raw = b64u_decode($s64);
$der = (function (string $raw): string {
    $int = fn ($b) => (ord(ltrim($b, "\0")[0] ?? "\0") & 0x80 ? "\0" : '') . ltrim($b, "\0");
    $r = $int(substr($raw, 0, 32));
    $s = $int(substr($raw, 32));
    $seq = "\x02" . chr(strlen($r)) . $r . "\x02" . chr(strlen($s)) . $s;
    return "\x30" . chr(strlen($seq)) . $seq;
})($raw);
check('VAPID signature is valid ES256', strlen($raw) === 64 && openssl_verify("$h64.$p64", $der, p256_public_pem(b64u_decode($m[2])), OPENSSL_ALGO_SHA256) === 1);

check('real push services allowed', push_endpoint_allowed('https://fcm.googleapis.com/fcm/send/x') && push_endpoint_allowed('https://web.push.apple.com/abc'));
check('anything else refused', !push_endpoint_allowed('https://127.0.0.1/x') && !push_endpoint_allowed('http://fcm.googleapis.com/x') && !push_endpoint_allowed('https://evil-fcm.googleapis.com.evil.fr/x'));

exec('rm -rf ' . escapeshellarg(KK_DATA));
echo $failures ? "\n$failures échec(s)\n" : "\nTout est bon.\n";
exit($failures ? 1 : 0);
