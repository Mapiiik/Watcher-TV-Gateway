<?php
declare(strict_types=1);

namespace WatcherTvGateway\Provider;

enum ProviderOutcome
{
    /** The customer is sent on to the provider. */
    case Redirect;

    /** The provider does not know the customer yet. */
    case UnknownCustomer;

    /** The provider will not take the customer's details, and no later attempt changes that. */
    case Refused;

    /** The provider did not answer, or failed for a reason that says nothing about the customer. */
    case Unavailable;
}
