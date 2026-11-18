# Mai costruito: fissa versione e digest dell'immagine Semgrep usata da
# security.yml (job semgrep), così Dependabot (ecosistema docker) la aggiorna
# come le altre immagini base. Il workflow legge la riga FROM.
FROM semgrep/semgrep:1.185.0@sha256:67518c174185a2043fdbed16c70631b79209499b3671178e7194379b48295a0d
# Solo per i controlli misconfig di Trivy (trivy-fs): l'immagine non viene eseguita da qui.
USER 65534
HEALTHCHECK NONE
