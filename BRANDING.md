# fly001 branding

Runtime branding is configured through environment variables and Xboard's admin settings.

- `APP_NAME`: fallback product name. Default: `fly001`.
- `APP_DESCRIPTION`: fallback product description.
- `APP_URL`: internal or public panel URL. Local default: `http://127.0.0.1:7001`.
- `XBOARD_BIND_ADDRESS`: host bind address used by Compose. Local default: `127.0.0.1`.
- `XBOARD_PORT`: host HTTP port. Default: `7001`.
- `XBOARD_IMAGE`: deployable image used by `compose.sample.yaml`.

Admin settings override the environment fallbacks. Production must use an HTTPS URL and must not expose the local development configuration directly.

Commercial authorization documents are maintained outside the public source repository. Existing upstream license and attribution files remain unchanged.
