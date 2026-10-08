# Outgoing HTTP

Engine-defined: WordPress has no counterpart to `Minn\Http`, so there is no
oracle here. `wp_remote_*()`, `WP_Http` and the Requests library keep the
reference's behaviour (`contracts/runtime.md`) and reach the same transport.

## The layers

| Layer | What it does |
|---|---|
| `Minn\Http` | A client, handed to the code that uses it: `$minn->http` for an extension (bound by its `needs.http`), a constructor argument for an engine class (the real network by default). Six verbs with named arguments, the destination rules, redirects followed hop by hop |
| `$http->send(Outbound)` | Sends exactly what the request says with none of the verbs' rules. `WpOrg\Requests\Transport\Curl` arrives here (on a client of its own) after applying the library's rules, and `WP_Http` through it after WordPress's. A client bound to hosts refuses it, since curl follows a sent request's redirects |
| `Http\Transport` | curl. Never called directly outside `Minn\Http` |
| `Http\Download` | Packages and language packs: https at every hop, optional host prefixes, a size cap, messages written for the person who asked |

## The rules the verbs apply

- **Schemes**: http and https only.
- **`hosts:`**: when given, every hop's URL must start with one of the prefixes (compared case-insensitively).
- **A bound client** (an extension's `$minn->http`, or `$fake->http(within: [...])`): every hop must also start with one of the client's prefixes, whatever the call's `hosts:` says. A hop outside fails with errno 0 and `{host} is not one of the hosts this client was granted (needs.http in minn.json).`
- **A refused client** (an extension whose `minn.json` has no `needs.http`): every verb and `send()` throw a `LogicException` naming the key to add, before anything is sent.
- **Private addresses**: an address outside the global range (PHP's `FILTER_FLAG_GLOBAL_RANGE`: loopback, private and shared ranges, link-local including 169.254.169.254, IPv4-mapped IPv6, reserved) is refused unless its host or the address itself is listed in `private:`. A literal address is judged before sending; a name is resolved (IPv4, else IPv6), every address is judged, and curl is pinned to those addresses with `CURLOPT_RESOLVE`, so a second DNS answer cannot redirect the connection. A listed host is not resolved or pinned.
- **Redirects**: followed by the engine, never by curl, so every hop meets the rules. `redirects: 0` returns the 3xx. Past the limit the exchange fails with `CURLE_TOO_MANY_REDIRECTS`. 303 becomes GET without a body, and so do 301 and 302 after a POST; 307 and 308 keep the method and body. `Authorization`, `Cookie` and `Proxy-Authorization` are dropped when a hop changes scheme, host or port.
- **Payloads**: one of `json:` (encoded by `Minn\Json::encode` in its readable form, invalid UTF-8 repaired; sets `Content-Type: application/json`), `form:` (`application/x-www-form-urlencoded`) or `body:` (sent as given). Two at once throw `InvalidArgumentException`. A Content-Type the caller set is kept.
- **`maxBytes:`**: curl refuses an announced length over the cap, and the progress callback aborts an unannounced one. Either way the exchange fails with `CURLE_FILESIZE_EXCEEDED`.
- **User agent**: `Minn/{MINN_ENGINE_VERSION}` unless `userAgent:` says otherwise.

## The Exchange

`errno` is 0 when the engine refused the request before sending it and curl's error number when the transport failed. `url` is where the final response came from. `header()` matches any spelling and joins repeated values with `, `. `cookie()` percent-decodes, and the last cookie with a name wins. `throw()` raises `Http\RequestFailed`, a `RuntimeException` carrying the exchange, with the message `{host} answered {code}.` or the transport error.

## The fake

`new Http\Fake([pattern => answer])` answers in place of the network for the clients it makes: `$fake->http()` (or `$fake->http(within: [...])` for a bound one) is handed to the class under test, and nothing else in the process sees the fake, so there is nothing to restore. In a pattern, `*` matches anything, and the scheme and the query string may be left off. An answer is an array (JSON), a string (the body), an int (the status), an `Exchange` from `Http\Fake::reply()`, or a closure taking the `Outbound`. A request no pattern matches fails with `CURLE_COULDNT_CONNECT`. `$fake->sent($pattern)` lists what went out. The destination rules still run under a fake, but names are not resolved. Plugin code's `wp_remote_*()` is not answered by it; WordPress's own `pre_http_request` filter is the seam there.
