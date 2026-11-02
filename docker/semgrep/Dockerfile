# Mai costruito: fissa versione e digest dell'immagine Semgrep usata da
# security.yml (job semgrep), così Dependabot (ecosistema docker) la aggiorna
# come le altre immagini base. Il workflow legge la riga FROM.
FROM semgrep/semgrep:1.182.1@sha256:451b3d875c00dac7ed5db5293e584a07de11449a409c10b3c2d2652f429630b6
# Solo per i controlli misconfig di Trivy (trivy-fs): l'immagine non viene eseguita da qui.
USER 65534
HEALTHCHECK NONE
