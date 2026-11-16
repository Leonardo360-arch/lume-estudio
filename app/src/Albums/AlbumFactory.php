<?php
declare(strict_types=1);
namespace Danny\Albums;
use Danny\Database;
/** Composition root: controllers never construct infrastructure dependencies. */
final class AlbumFactory {
    public static function repository(): PdoAlbumRepository { return new PdoAlbumRepository(Database::connection()); }
    public static function storage(): PhotoStorage { return new LocalPhotoStorage((string)env('ALBUM_STORAGE_PATH',dirname(__DIR__,2).'/storage/albums')); }
    public static function policy(): AlbumPolicy { return new AlbumPolicy(new SystemClock()); }
    public static function service(): AlbumService { return new AlbumService(self::repository(),self::storage(),new SystemClock(),self::policy()); }
}
