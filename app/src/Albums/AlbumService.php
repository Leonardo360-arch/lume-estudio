<?php
declare(strict_types=1);
namespace Danny\Albums;
final class AlbumService {
    public function __construct(private AlbumRepository $albums, private PhotoStorage $storage, private Clock $clock, private AlbumPolicy $policy) {}
    public function create(int $clientId, ?int $bookingId, string $title, string $description): string {
        $title=trim($title); $description=trim($description);
        if ($title === '' || mb_strlen($title)>160 || mb_strlen($description)>1000) { throw new \InvalidArgumentException('Informe título de até 160 e descrição de até 1.000 caracteres.'); }
        $id=bin2hex(random_bytes(16)); $this->albums->create($id,$clientId,$bookingId,$title,$description); return $id;
    }
    public function upload(string $albumId, array $file): void {
        $this->albums->transaction(function () use ($albumId,$file) {
            $album=$this->albums->find($albumId,true);
            if (!$album || $album['status']!=='DRAFT') { throw new \DomainException('Adicione fotos somente ao rascunho, antes de publicar.'); }
            $photos=$this->albums->photos($albumId);
            if (count($photos)>=(int)env('ALBUM_MAX_PHOTOS',500)) { throw new \DomainException('Limite de fotos por álbum atingido.'); }
            $photo=$this->storage->storeUpload($file);
            try {
                if (array_sum(array_column($photos,'bytes')) + $photo['bytes'] > (int)env('ALBUM_MAX_MB',5000)*1024*1024) { throw new \DomainException('Limite de armazenamento por álbum atingido.'); }
                $this->albums->addPhoto($albumId,$photo);
            } catch (\Throwable $e) {
                $this->storage->delete($photo['storage_key']);
                if ($photo['thumbnail_key']) { $this->storage->delete($photo['thumbnail_key']); }
                throw $e;
            }
        });
    }
    public function publish(string $id): void {
        $this->albums->transaction(function () use ($id) {
            $album=$this->albums->find($id,true);
            if (!$album || $album['status']!=='DRAFT') { throw new \DomainException('Somente um rascunho pode ser publicado.'); }
            if (!$this->albums->photos($id)) { throw new \DomainException('Envie ao menos uma foto antes de publicar.'); }
            $now=$this->clock->now(); $expires=$now->modify('+14 days');
            $this->albums->publish($id,$now->format('Y-m-d H:i:s'),$expires->format('Y-m-d H:i:s'),$expires->modify('+7 days')->format('Y-m-d H:i:s'),false);
        });
    }
    public function extend(string $id, int $days): void {
        if ($days<1 || $days>90) { throw new \InvalidArgumentException('Informe de 1 a 90 dias de prorrogação.'); }
        $this->albums->transaction(function () use ($id,$days) {
            $album=$this->albums->find($id,true);
            if (!$album || !$this->policy->recoverable($album)) { throw new \DomainException('Álbum fora do período de recuperação.'); }
            $now=$this->clock->now(); $base=max($now,$this->policy->date($album['expires_at'])); $expires=$base->modify('+' . $days . ' days');
            $this->albums->publish($id,$now->format('Y-m-d H:i:s'),$expires->format('Y-m-d H:i:s'),$expires->modify('+7 days')->format('Y-m-d H:i:s'),true);
        });
    }
    public function authorize(string $id, int $clientId, bool $admin=false): array {
        $album=$this->albums->find($id);
        if (!$album || ($admin ? $album['status']==='PURGED' || ($album['status']==='PUBLISHED' && !$this->policy->recoverable($album)) : !$this->policy->readable($album,$clientId))) {
            throw new \DomainException('Álbum indisponível.');
        }
        return $album;
    }
    public function purgeExpired(bool $execute=false): int {
        $count=0;
        foreach ($this->albums->dueForPurge($this->clock->now()->format('Y-m-d H:i:s')) as $candidate) {
            if (!$execute) { $count++; continue; }
            $count += $this->albums->transaction(function () use ($candidate) {
                $album=$this->albums->find($candidate['id'],true);
                if (!$album || $album['status']!=='PUBLISHED' || $this->policy->recoverable($album)) { return 0; }
                foreach ($this->albums->photos($album['id']) as $photo) {
                    $this->storage->delete($photo['storage_key']);
                    if ($photo['thumbnail_key']) { $this->storage->delete($photo['thumbnail_key']); }
                }
                $this->albums->markPurged($album['id']); return 1;
            });
        }
        return $count;
    }
}
