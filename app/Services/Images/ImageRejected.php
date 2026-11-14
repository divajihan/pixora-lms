<?php

namespace App\Services\Images;

use RuntimeException;

/**
 * The image cannot be accepted (corrupt, too large, unsupported by the
 * server's GD). The message is user-facing (Indonesian).
 */
class ImageRejected extends RuntimeException {}
