FROM nginx:mainline-alpine3.22 AS nginx

WORKDIR /opt/repos

RUN apk --no-cache add shadow && usermod -u 10014 nginx && \
    groupmod -g 10014 nginx && apk del shadow

RUN touch /var/run/nginx.pid && \
        chown -R nginx:nginx /usr/share/nginx/html && \
        chown -R nginx:nginx /var/cache/nginx && \
        chown -R nginx:nginx /var/log/nginx && \
        chown -R nginx:nginx /etc/nginx && \ 
        chown -R nginx:nginx /var/run/nginx.pid     

USER nginx