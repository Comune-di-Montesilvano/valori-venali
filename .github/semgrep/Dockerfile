# Mai costruito: fissa versione e digest dell'immagine Semgrep usata da
# security.yml (job semgrep), così Dependabot (ecosistema docker) la aggiorna
# come le altre immagini base. Il workflow legge la riga FROM.
FROM semgrep/semgrep:1.181.1@sha256:3db5c7af55eb1f512eac0abf0ab69a97b52c74b5e9a27314c221aee1a78d7ab2
# Solo per i controlli misconfig di Trivy (trivy-fs): l'immagine non viene eseguita da qui.
USER 65534
HEALTHCHECK NONE
