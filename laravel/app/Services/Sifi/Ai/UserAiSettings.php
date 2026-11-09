<?php
namespace App\Services\Sifi\Ai;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;

final class UserAiSettings
{
    private function path(int $id): string { if($id<1)throw new \InvalidArgumentException('Invalid owner');return storage_path('app/private/sifi-ai/'.$id.'.enc'); }
    public function read(int $id): array {
        $path=$this->path($id);
        if(!is_file($path))return ['enabled'=>false,'providers'=>[],'assignments'=>[]];
        return json_decode(Crypt::decryptString(File::get($path)),true,32,JSON_THROW_ON_ERROR);
    }
    public function write(int $id,array $data): void {
        $path=$this->path($id);File::ensureDirectoryExists(dirname($path),0700);File::replace($path,Crypt::encryptString(json_encode($data,JSON_THROW_ON_ERROR)),0600);
    }
    public function publicView(int $id): array {
        $data=$this->read($id);
        foreach($data['providers'] as &$p){$p['credential_saved']=($p['key']??'')!=='';unset($p['key']);}
        return $data;
    }
    public function revoke(int $id): void { $this->write($id,['enabled'=>false,'providers'=>[],'assignments'=>[]]); }
}
