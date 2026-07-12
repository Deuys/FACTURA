FROM node:24-alpine

WORKDIR /app

COPY frontend/package*.json ./

RUN npm ci

COPY frontend/ ./

EXPOSE 4200

CMD ["npm", "start", "--", "--host", "0.0.0.0", "--poll", "1000"]