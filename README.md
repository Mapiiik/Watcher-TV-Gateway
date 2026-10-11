# Watcher TV Gateway

Authentication proxy for TV services. A customer opens the gateway from home, it recognises them by
the IP address of their connection in [Watcher CRM](https://github.com/Mapiiik/Watcher-CRM) and
signs them in to the TV provider - or sends them to its registration, filled in with what the CRM
knows about them. A player can fetch the customer's channel playlist the same way.

Runs on **FrankenPHP** behind HAProxy, like the other services of the family, and the image is
built by GitHub Actions.

---

## How it works

| Address | What it does |
|---|---|
| `/` | recognises the customer and redirects them to the provider (HTTP 303) |
| `/channels.m3u` | redirects a player to the customer's M3U playlist (HTTP 302) |

1. The client's address comes from the PROXY protocol header the proxy sends. Caddy takes it only
   from `PROXY_ALLOW`, so nobody else can claim an address.
2. Watcher CRM is asked who uses the address (`GET /api/customer-portal/identify`), as a CRM user
   with the `customer-portal` role and its API key. Only a customer with an installation address
   counts, and customers of the companies in `EXCLUDED_COMPANIES` are left out.
3. The provider (`TV_PROVIDER`) gets the customer number as the customer's identity there,
   with their name, e-mail and installation address, and answers where to send them.

Anything that cannot be done ends on a page (or, for a player, in a playlist with one entry)
that says so and shows the address the customer came from. The reason goes to the log, never to
the customer.

## Providers

| `TV_PROVIDER` | Service | Settings |
|---|---|---|
| `sledovanitv` | SledovaniTV ([partner API](https://redoc.moderntv.app/?url=https://sledovanitv.cz/partner/api/_/apidoc)) | `SLEDOVANITV_PARTNER`, `SLEDOVANITV_PASSWORD`, optionally `SLEDOVANITV_API_URL` |

A new provider implements `WatcherTvGateway\Provider\TvProvider` (sign in, playlist) with its own
settings read in `fromEnvironment()`, and gets its name in `WatcherTvGateway\Provider\Providers`.

## Making it yours

Nothing about the operator is in the code. Its name and support phone come from `OPERATOR_NAME`
and `SUPPORT_PHONE`, the customers left out from `EXCLUDED_COMPANIES`.

The texts of the pages and playlist entries in `templates/` are English and translated, as in
Watcher CRM, from `resources/locales/<locale>/default.po` (`cs_CZ` so far); `APP_DEFAULT_LOCALE`
picks the language. After changing the texts, `php bin/extract-texts.php` brings `default.pot` and
the catalogues up to date and lists what is left to translate. The pictures a player shows for a
playlist entry carry text too: `public/images/` holds the English ones, `public/images/<language>/`
those of a language.

For texts of your own, mount a directory with the same files as `templates/` and point
`TEMPLATES_DIR` at it. A template gets its values in `$v` (`ip`, `operator`, `supportPhone`,
`service`, `language`, `images`), translates with `$t` and escapes for HTML with `$e`.

## Running it

1. In Watcher CRM, create a user with the **customer-portal** role and give it an API key.
2. Copy `.env.example` to `.env` and fill it in. `.env` stays out of git.
3. Copy `compose.production.yaml` to `compose.yaml` and start it:

```bash
docker compose up -d
```

Behind HAProxy (TLS passed through by SNI, `send-proxy-v2`), set `LISTENER_MODE=behind-proxy` and
`PROXY_ALLOW` to the proxy's network. The certificate is then obtained with the TLS-ALPN challenge.
Do not publish an AAAA record unless the CRM knows the customers' IPv6 addresses: a customer
coming over IPv6 would not be recognised.

## Development

```bash
composer install
composer test     # PHPUnit
composer stan     # PHPStan, level max
```

Without PHP on the machine:

```bash
docker run --rm -v "$PWD:/app" -w /app composer:2 sh -c 'composer install && composer test && composer stan'
```

To try the image locally, `compose.production-build.yaml` builds it, and a request can claim any
client address through the PROXY protocol from this machine:

```bash
curl -k --haproxy-protocol --haproxy-clientip 192.0.2.10 https://localhost/
```

## License

[GNU Affero General Public License v3.0](LICENSE.md)
