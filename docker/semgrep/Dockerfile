# Mai costruito: fissa versione e digest dell'immagine Semgrep usata da
# security.yml (job semgrep), così Dependabot (ecosistema docker) la aggiorna
# come le altre immagini base. Il workflow legge la riga FROM.
FROM semgrep/semgrep:1.183.0@sha256:2e45517a85e13a9cca14a2a00df6bb48affd66b8ebfae1b0991cd57578362fb0
# Solo per i controlli misconfig di Trivy (trivy-fs): l'immagine non viene eseguita da qui.
USER 65534
HEALTHCHECK NONE
