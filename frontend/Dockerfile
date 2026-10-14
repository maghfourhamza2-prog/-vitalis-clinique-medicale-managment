# syntax=docker/dockerfile:1

# Node 22 LTS - matches the toolchain Vite 8 targets.
FROM node:22-alpine

ENV NODE_ENV=development \
    npm_config_update_notifier=false \
    npm_config_fund=false \
    npm_config_audit=false

WORKDIR /app

# Seeds the node_modules volume on first boot and caches the layer afterwards.
# `npm ci` installs exactly what package-lock.json pins - same command as the CI
# workflow - so the image cannot drift from the versions tested there.
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint \
    && chmod +x /usr/local/bin/entrypoint \
    && chown -R node:node /app

USER node

EXPOSE 5173

ENTRYPOINT ["entrypoint"]
CMD ["npm", "run", "dev"]
