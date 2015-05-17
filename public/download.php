<?php
use Selene\Exceptions\FatalException;
use Selene\Exceptions\FileException;
use Selene\Media;

$rootDir = __DIR__ . "/../../../..";

$URI      = get ($_GET, 'URI');
$filename = get ($_GET, 'name');
$id       = get ($_GET, 'id');
$cache    = get ($_GET, 'cache', false);
$mode     = get ($_GET, 'mode', 'attachment'); // inline/attachment

if (empty($URI)) {
  if (is_null ($id))
    throw new FatalException('No id or URI were specified');
  $filename = urlencode (Media::getOriginalFileName ($id));
  $URI      = Media::getFileURI ($id);
}
$filepath = $application->toFilePath ($URI);
if (!file_exists ($filepath))
  throw new FileException(FileException::FILE_NOT_FOUND);

Media::streamFile ($filepath, $cache ? Media::CACHING_ENABLED : Media::CACHING_DISABLED,
  $mode == 'attachment' ? $filename : '');