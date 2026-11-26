<?php

namespace Ispgrid\HotspotVouchers\Support;

use RuntimeException;

/**
 * A batch action that no longer fits the batch (handed out, switched or used meanwhile). The message is for staff.
 */
final class CannotChangeBatch extends RuntimeException {}
