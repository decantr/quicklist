const cache_name = "quicklist-v3";
const precache_urls = [
	"/dashboard",
	"/static/app.css",
	"/static/app.js",
	"/static/htmx.min.js",
	"/static/alpine.min.js",
	"/static/favicon.svg",
];

self.addEventListener("install", (event) => {
	event.waitUntil(caches.open(cache_name).then((cache) => cache.addAll(precache_urls)));
	self.skipWaiting();
});

self.addEventListener("activate", (event) => {
	event.waitUntil(
		caches.keys()
			.then((names) => Promise.all(names.filter((name) => name !== cache_name).map((name) => caches.delete(name))))
			.then(() => self.clients.claim()),
	);
});

// Network first so pages stay fresh; the cache only answers when the network fails.
self.addEventListener("fetch", (event) => {
	const request = event.request;
	if (request.method !== "GET" || new URL(request.url).origin !== self.location.origin) {
		return;
	}

	event.respondWith(
		fetch(request)
			.then((response) => {
				if (response.ok && !response.redirected) {
					const copy = response.clone();
					caches.open(cache_name).then((cache) => cache.put(request, copy));
				}
				return response;
			})
			.catch(() => caches.match(request, { ignoreVary: true })),
	);
});
