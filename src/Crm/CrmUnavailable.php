<?php
declare(strict_types=1);

namespace WatcherTvGateway\Crm;

use RuntimeException;

/**
 * The CRM did not answer, or answered something the gateway cannot read.
 */
final class CrmUnavailable extends RuntimeException
{
}
