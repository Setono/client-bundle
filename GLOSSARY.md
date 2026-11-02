# Client tracking

Recognizes the same browser across visits and keeps key/value data about it between visits.

## Language

**Client**:
A browser the application recognizes across visits, identified by its client id.
_Avoid_: user, customer, session

**Client id**:
The identifier of a client. It's opaque to the application and the same on every visit.
_Avoid_: visitor id, tracking id

**Current client**:
The client the request being handled belongs to. It's the source of truth for the client id, and the client cookie always follows it.
_Avoid_: resolved client, active client

**New client**:
A client that the request introduced: the request had no valid client cookie, so it got a new client id.
_Avoid_: anonymous client (that's a different term), first-time visitor

**Anonymous client**:
A client created where there is no request, e.g. in a console command or a worker. Its client id is never stored.
_Avoid_: new client, default client

**Client cookie**:
The cookie that carries a client's id together with its first-seen and last-seen timestamps.
_Avoid_: client id cookie, tracking cookie

**First seen**:
When the client id in the client cookie was first issued. It starts over when the current client's id differs from the cookie's.

**Last seen**:
When the client was last seen, i.e. the latest request that refreshed its client cookie.

**Metadata**:
Key/value data kept per client between visits, each key with an optional expiry.
_Avoid_: attributes, properties, client data
