FROM debian:13-slim

ARG TARGETARCH
ENV DEBIAN_FRONTEND=noninteractive
ENV TERM=xterm-256color
ENV COLORTERM=truecolor

# ── Basis-Tools ─────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    curl wget git jq bat vim less unzip \
    ca-certificates gnupg lsb-release \
    docker.io \
    && rm -rf /var/lib/apt/lists/*

# ── ttyd ────────────────────────────────────────────────
RUN TTYD_VERSION=$(curl -s https://api.github.com/repos/tsl0922/ttyd/releases/latest | jq -r .tag_name) \
    && ARCH=$(uname -m) \
    && case "$ARCH" in \
         x86_64)  TTYD_ARCH="x86_64" ;; \
         aarch64) TTYD_ARCH="aarch64" ;; \
         *)       TTYD_ARCH="x86_64" ;; \
       esac \
    && curl -fsSL "https://github.com/tsl0922/ttyd/releases/download/${TTYD_VERSION}/ttyd.${TTYD_ARCH}" \
       -o /usr/local/bin/ttyd \
    && chmod +x /usr/local/bin/ttyd

# ── GitHub CLI ──────────────────────────────────────────
RUN curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg \
    | dd of=/usr/share/keyrings/githubcli-archive-keyring.gpg \
    && chmod go+r /usr/share/keyrings/githubcli-archive-keyring.gpg \
    && echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" \
    | tee /etc/apt/sources.list.d/github-cli.list > /dev/null \
    && apt-get update && apt-get install -y gh \
    && rm -rf /var/lib/apt/lists/*

# ── gh copilot Extension ─────────────────────────────────
# Wird beim ersten Start installiert (braucht Auth-Token)
# RUN gh extension install github/gh-copilot

# ── Demo-Hilfsskripte ────────────────────────────────────
COPY demo-scripts/ /opt/demo/
RUN chmod +x /opt/demo/*.sh 2>/dev/null || true

# ── Shell-Konfiguration ──────────────────────────────────
RUN echo 'export PS1="\[\033[00;32m\]ls-ki\[\033[00m\]:\[\033[00;34m\]\w\[\033[00m\]\$ "' >> /root/.bashrc \
    && echo 'alias ll="ls -la --color=auto"' >> /root/.bashrc \
    && echo 'alias demo="ls /opt/demo/"' >> /root/.bashrc \
    && echo 'alias health="curl -s http://nginx/api/health | jq ."' >> /root/.bashrc \
    && echo 'alias models="curl -s http://nginx/api/models | jq ."' >> /root/.bashrc \
    && echo '' >> /root/.bashrc \
    && echo '# ── LS-KI Demo Terminal ────────────────────────────────' >> /root/.bashrc \
    && echo 'echo ""' >> /root/.bashrc \
    && echo 'echo "  ██╗     ███████╗      ██╗  ██╗██╗"' >> /root/.bashrc \
    && echo 'echo "  ██║     ██╔════╝      ██║ ██╔╝██║"' >> /root/.bashrc \
    && echo 'echo "  ██║     ███████╗      █████╔╝ ██║"' >> /root/.bashrc \
    && echo 'echo "  ██║     ╚════██║      ██╔═██╗ ██║"' >> /root/.bashrc \
    && echo 'echo "  ███████╗███████║      ██║  ██╗██║"' >> /root/.bashrc \
    && echo 'echo "  ╚══════╝╚══════╝      ╚═╝  ╚═╝╚═╝"' >> /root/.bashrc \
    && echo 'echo ""' >> /root/.bashrc \
    && echo 'echo "  Dataport CCW · InnoLab · $(date +%Y)"' >> /root/.bashrc \
    && echo 'echo ""' >> /root/.bashrc \
    && echo 'echo "  Shortcuts: health | models | demo"' >> /root/.bashrc \
    && echo 'echo ""' >> /root/.bashrc

EXPOSE 7681

CMD ["ttyd", "--port", "7681", "--writable", "--base-path", "/terminal", "bash"]
