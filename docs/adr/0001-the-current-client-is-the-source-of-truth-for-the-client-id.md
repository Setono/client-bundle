# The current client is the source of truth for the client id

In 2.0, the client cookie always follows the current client, which is the client that `ClientContextInterface` returns, decorators included. When the current client's id differs from the one in the request's client cookie, the cookie is rewritten with the current client's id and fresh timestamps. In 1.x, the cookie writer read the cookie and ignored the client context whenever a cookie existed, so a decorated context and the cookie could disagree.

The bundle gives a request one client by keeping it per `Request` object, outermost around any decorators, so it needs no `kernel.reset` in long-running runtimes. The request's client stays available to `kernel.terminate` listeners, although Symfony removes the request from the request stack before it dispatches that event. Without a request there is no visitor to recognize, so every call gets an anonymous client of its own, which is never stored.

## Considered Options

- **The cookie wins** (the 1.x behaviour): rejected. A decorator, the documented way to choose another client, couldn't change the id stored in the cookie.
- **A memo cleared by `kernel.reset`** (1.x): rejected. Symfony resets whichever service decorates the reset one, and decorators had to sit inside the memo by picking a priority. Keying by the `Request` object needs no reset.
- **A memo inside the bundle's client context, with decorators outside it**: rejected. A decorator that creates a client on every call would give a request several clients, and metadata set on one of them would be lost.
- **Throw without a request**: rejected. Callers like a wishlist factory run in fixtures and console commands.
- **One anonymous client per process**: rejected. Messenger workers would share it across messages.
- **No client in `kernel.terminate`, or a `getClient(?Request)` parameter for it**: rejected. Listeners that send analytics after the response would silently get an anonymous client, or every decorator would have to pass the request on.
