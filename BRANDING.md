# fly001 branding

Runtime branding is configured through environment variables and Xboard's admin settings.

- `APP_NAME`: fallback product name. Default: `fly001`.
- `APP_DESCRIPTION`: fallback product description.
- `APP_URL`: internal or public panel URL. Local default: `http://127.0.0.1:7001`.
- `XBOARD_BIND_ADDRESS`: host bind address used by Compose. Local default: `127.0.0.1`.
- `XBOARD_PORT`: host HTTP port. Default: `7001`.
- `XBOARD_IMAGE`: deployable image used by `compose.sample.yaml`.
- `TLS_BIND_ADDRESS`: host bind address of the optional HTTPS front in `compose.tls.yaml`. Default: `127.0.0.1`.
- `TLS_PORT`: host HTTPS port of that front. Default: `7443`.
- `FLY001_LOCAL_CA_DIR`: directory holding the local CA `Caddyfile` and `certs/`. Default: `../local-ca`, kept outside this repository.

Admin settings override the environment fallbacks. Production must use an HTTPS URL and must not expose the local development configuration directly.

`compose.tls.yaml` is development tooling only: it terminates TLS with a self-signed local CA so clients that require HTTPS can be tested against the panel, while the internal HTTP listener on `XBOARD_PORT` keeps serving the node. Never ship the local CA, and set `APP_URL` to the matching HTTPS origin before generating links or subscription metadata.

Commercial authorization documents are maintained outside the public source repository. Existing upstream license and attribution files remain unchanged.
