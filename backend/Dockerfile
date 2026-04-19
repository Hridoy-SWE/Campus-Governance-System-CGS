//sajib
FROM golang:1.23-alpine AS builder
WORKDIR /app
RUN apk add --no-cache gcc musl-dev
COPY go.mod go.sum* ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=1 GOOS=linux go build -o server ./main.go

FROM alpine:3.20
WORKDIR /app
RUN apk add --no-cache ca-certificates sqlite-libs
COPY --from=builder /app/server /app/server
COPY .env /app/.env
COPY uploads /app/uploads
EXPOSE 8080
CMD ["/app/server"]
