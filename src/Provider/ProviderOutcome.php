<?php
declare(strict_types=1);

namespace WatcherTvGateway\Provider;

enum ProviderOutcome
{
    /** The customer is sent on to the provider. */
    case Redirect;

    /** The provider does not know the customer yet. */
    case UnknownCustomer;

    /** The provider did not answer, or refused for a reason the customer cannot do anything about. */
    case Unavailable;
}
