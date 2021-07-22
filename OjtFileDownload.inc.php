<?php

use PKP\Services\PKPFileService;

import('lib.pkp.classes.plugins.GenericPlugin');

class OjtFileDownload extends GenericPlugin
{
  public function register($category, $path, $mainContextId = null)
  {
    if (parent::register($category, $path, $mainContextId)) {
      if ($this->getEnabled()) {
        HookRegistry::register('File::formatFilename', [$this, 'formatFilenameHandler']);
        HookRegistry::register('File::download', [$this, 'downloadHandler']);
      }
      return true;
    }
    return false;
  }

  /**
   * Get the display name of this plugin.
   * @return String
   */
  public function getDisplayName()
  {
    return 'OJT Download File';
  }

  /**
   * Get a description of the plugin.
   */
  public function getDescription()
  {
    return 'Download OJS files name with old formatted file name';
  }

  public function downloadHandler($hookName, $args)
  {
    $path = $args[0];
    $filename = $args[1];
    $inline = $args[2];

    $fileService = new PKPFileService();


    // Stream the file to the end user.
    $mimetype = $fileService->fs->getMimetype($path) ?? 'application/octet-stream';
    $filesize = $fileService->fs->getSize($path);
    $encodedFilename = $filename;

    header("Content-Type: $mimetype");
    header("Content-Length: $filesize");
    header('Accept-Ranges: none');
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . ";filename=\"${encodedFilename}\";filename*=UTF-8''\"${encodedFilename}\"");
    header('Cache-Control: private'); // Workarounds for IE weirdness
    header('Pragma: public');

    fpassthru($fileService->fs->readStream($path));
    exit();


    return true;
  }

  public function formatFilenameHandler($hookname, $args)
  {
    $path = $args[1];

    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    $array = explode('/', $path);

    $submissionId =  $array[3];

    $submissionFilesIterator = Services::get('submissionFile')->getMany([
      'submissionIds' => [$submissionId],
    ]);

    $genreDao = DAORegistry::getDAO('GenreDAO');

    foreach ($submissionFilesIterator as $submissionFile) {
      if ($submissionFile->getData('path') == $path) {
        $genre = $genreDao->getById($submissionFile->getData('genreId'));
        $timestamp = date('Ymd', strtotime($submissionFile->getData('createdAt')));

        $args[0] =  $submissionId . '-' .
          ($genre ? ($genre->getLocalizedName() . '-') : '') .
          $submissionFile->getData('fileId') . '-' .
          $submissionFile->getBestId() . '-' .
          $submissionFile->getData('fileStage') . '-' .
          $timestamp . '.' . $extension;
        return;
      }
    }


    return;
  }
}
