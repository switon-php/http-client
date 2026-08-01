# Design Rationale {#design-rationale}
`switon/http-client` keeps the public path small: one client contract, one request object, one response object, and typed failures.

## See also {#see-also}

- [HTTP Client](README.md) — quick start, concepts, and configuration
- [FAQ](faq.md#http-client-faq) — short answers for common questions

---

## Core Choice {#core-choice}

The package standardizes the common HTTP shape first.

- JSON APIs use shortcut methods
- raw requests stay available through `request()`
- `Response` owns response reading and decoding helpers
- request-specific transport overrides stay in `RequestOptions`

## Trade-Off {#trade-off}

The package does not put every cURL detail on the front page.

- **Benefit:** the common path stays easy to read
- **Benefit:** docs stay short enough to scan
- **Cost:** advanced transport tuning is pushed to discovery and source-level contracts

## Deliberate Simplicity {#deliberate-simplicity}

Uploads, headers, cookies, and typed exceptions are the parts readers touch most.

- `LocalFile` and `MemoryFile` cover the two common upload shapes
- `json()`, `text()`, `status()`, and `header()` cover the common read path
- non-2xx responses and parse failures remain distinct failure classes
