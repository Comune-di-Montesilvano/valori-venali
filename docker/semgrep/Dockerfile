# Mai costruito: fissa versione e digest dell'immagine Semgrep usata da
# security.yml (job semgrep), così Dependabot (ecosistema docker) la aggiorna
# come le altre immagini base. Il workflow legge la riga FROM.
FROM semgrep/semgrep:1.184.0@sha256:cc1b68266f267878b39739373ea9985b9a78e5d64497dd875c8de100b22695ed
# Solo per i controlli misconfig di Trivy (trivy-fs): l'immagine non viene eseguita da qui.
USER 65534
HEALTHCHECK NONE
