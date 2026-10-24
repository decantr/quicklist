const cache_name = "quicklist-v4";
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

self.addEventListener("fetch", (event) => {
	const request = event.request;
	if (new URL(request.url).origin !== self.location.origin) {
		return;
	}

	if (request.method === "GET") {
		event.respondWith(networkFirst(request));
		return;
	}

	// Following htmx form posts here lets the page they redirect to be cached; plain navigations can't take a redirected response.
	if (request.mode !== "navigate") {
		event.respondWith(fetch(request).then(rememberRedirectTarget));
	}
});

async function networkFirst(request) {
	try {
		const response = await fetch(request);
		if (response.ok && !response.redirected) {
			await remember(request.url, response.clone());
		}
		return response;
	} catch (error) {
		const cached = await caches.match(request, { ignoreVary: true });
		if (cached) {
			return cached;
		}
		throw error;
	}
}

async function rememberRedirectTarget(response) {
	if (response.ok && response.redirected) {
		await remember(response.url, response.clone());
	}
	return response;
}

// Cached copies are rebuilt so a redirected response is never served back for a navigation.
async function remember(url, response) {
	let body = await response.blob();
	if ((response.headers.get("Content-Type") || "").startsWith("text/html")) {
		const html = await body.text();
		body = html.replace(/<div class="toast"[^>]*>[^<]*<\/div>/g, "");
	}
	const cache = await caches.open(cache_name);
	await cache.put(url, new Response(body, { status: response.status, statusText: response.statusText, headers: response.headers }));
}
