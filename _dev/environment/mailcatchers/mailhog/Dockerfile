FROM golang:tip-alpine3.22 AS builder

RUN apk update \ 
&& apk upgrade \ 
&& apk add git 

RUN go install github.com/mailhog/MailHog@latest

FROM alpine:3.22.1
# Add mailhog user/group with uid/gid 1000.
# This is a workaround for boot2docker issue #581, see
# https://github.com/boot2docker/boot2docker/issues/581
RUN addgroup -g 50148 mailhog && adduser -D -u 50148 -G mailhog mailhog -

COPY --from=builder /go/bin/MailHog /bin/

USER mailhog

WORKDIR /home/mailhog

ENTRYPOINT ["MailHog"]

# Expose the SMTP and HTTP ports:
EXPOSE 1025 8025

#ENTRYPOINT ["tail", "-f", "/dev/null"]