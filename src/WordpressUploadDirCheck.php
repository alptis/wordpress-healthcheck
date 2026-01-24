<?php

namespace Alptis\Wordpress\Healthcheck;

class WordpressUploadDirCheck extends WritableDirCheck
{
    public function __construct()
    {
        parent::__construct(
            'wordpress_upload_dir',
            'WordPress upload directory',
            wp_upload_dir()['basedir']
        );
    }
}
