$m=App\Models\Merchant::first();
$s=app(App\Services\ApiKeyService::class);
$k=$s->generateKey($m, 'test');
file_put_contents('key_output.txt', "KEY:".$k['key']."\nSECRET:".$m->webhook_secret);
exit;