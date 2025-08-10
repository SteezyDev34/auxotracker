# Utilisez l'image officielle de Node.js 18
FROM node:18-alpine

# Définir le répertoire de travail
WORKDIR /app

# Copier les fichiers package.json et package-lock.json
COPY package*.json ./

# Installer les dépendances
RUN npm install

# Copier tous les fichiers du projet
COPY . .

# Construire l'application Vue.js
RUN npm run dev

# Exposer le port sur lequel l'application sera accessible
EXPOSE 8080

# Commande pour démarrer l'application Vue.js
CMD ["npm", "run", "dev"]
