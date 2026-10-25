FROM --platform=$BUILDPLATFORM golang:1.26-alpine AS build
ARG TARGETOS TARGETARCH
WORKDIR /src
COPY go.mod go.sum ./
RUN --mount=type=cache,target=/go/pkg/mod go mod download
COPY . .
RUN --mount=type=cache,target=/go/pkg/mod --mount=type=cache,target=/root/.cache/go-build \
	CGO_ENABLED=0 GOOS=$TARGETOS GOARCH=$TARGETARCH go build -trimpath -ldflags '-s -w' -o /quicklist .
RUN mkdir /data

FROM gcr.io/distroless/static-debian12:nonroot
COPY --from=build /quicklist /quicklist
COPY --from=build --chown=nonroot:nonroot /data /data
ENV QUICKLIST_ADDR=0.0.0.0:8080 QUICKLIST_DB=/data/database.sqlite
VOLUME /data
EXPOSE 8080
HEALTHCHECK --start-period=10s --start-interval=1s CMD ["/quicklist", "-healthcheck"]
ENTRYPOINT ["/quicklist"]
