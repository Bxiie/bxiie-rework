# Platform admin redirect implementation

Tenant-host requests for `/platform/admin...` redirect to `https://artsfol.io/platform/admin...` before tenant routing dispatches the request.

The front controller uses a normal `Response::html('', 302, ['Location' => $target])` response because `App\Http\Response` does not expose a `redirect()` factory method.

# End of file.
