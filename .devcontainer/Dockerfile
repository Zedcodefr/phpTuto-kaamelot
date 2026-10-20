# Poste de travail VS Code : ni PHP ni Node ici, comme sur un poste classique.
# Les applications tournent dans les conteneurs de compose.yaml, pilotés par `make`
# grâce au Docker de la machine (feature docker-outside-of-docker).
FROM mcr.microsoft.com/devcontainers/base:bookworm

# make : toutes les commandes du parcours · socat : localhost:<port> joint les ports publiés par la stack
RUN apt-get update \
    && apt-get install -y --no-install-recommends make socat postgresql-client \
    && rm -rf /var/lib/apt/lists/*
