# Project Paths

Base directory: `/Users/tomrobak/_code_`

## Main Projects

| Project | Path | Description |
|---------|------|-------------|
| 🏢 FCHub (Main SaaS) | `/Users/tomrobak/_code_/fchub` | Main FCHub SaaS platform |
| 🏠 FluentCommunity Vibe | `/Users/tomrobak/_code_/fluentcommunity-vibe` | FluentCommunity workspace |

## Plugins

| Project | Path | Description |
|---------|------|-------------|
| 🔌 FCHub Chat | `/Users/tomrobak/_code_/fchub-plugins/fchub-chat` | Chat plugin (DEV) |
| 🔌 FCHub Stream | `/Users/tomrobak/_code_/fchub-plugins/fchub-stream` | Stream plugin (DEV) |
| 🔌 FCHub Stream Public | `/Users/tomrobak/_code_/fchub-plugins/fchub-stream-public` | Stream plugin (Public) |
| 🔌 FCHub Companion | `/Users/tomrobak/_code_/fchub-plugins/fchub-companion` | Mobile API companion |

## Mobile & SDKs

| Project | Path | Description |
|---------|------|-------------|
| 📱 Mobile App | `/Users/tomrobak/_code_/fluentcommunity-vibe/fluent-community-mobile` | React Native app |
| 🧩 Licenses SDKs | `/Users/tomrobak/_code_/fchub-licenses-sdks` | Licensing SDKs |

## Documentation

| Project | Path | Description |
|---------|------|-------------|
| 📚 docs.fchub.co | `/Users/tomrobak/_code_/fchub-plugins/fchub-docs` | Documentation website |

---

## Test WordPress Environment

**Docker Compose:** `/Users/tomrobak/_code_/fluentcommunity-vibe/docker-compose.yml`

| Service | URL | Credentials |
|---------|-----|-------------|
| WordPress | http://localhost:8080 | (check wp-admin) |
| WordPress (public) | https://wpfc.vcode.sh | (via Cloudflare Tunnel) |
| phpMyAdmin | http://localhost:8090 | root / rootpassword |

**WordPress admin user:**
```
Username: tomrobak
Password: password
```

**MySQL:**
```
Host: localhost:3306
Database: wordpress
User: wordpress
Password: wordpress
Root Password: rootpassword
```

**Debug Log:**
```
/Users/tomrobak/_code_/fluentcommunity-vibe/wp-content/debug.log
```

**Docker Commands:**
```bash
# Start
docker-compose up -d

# Stop
docker-compose down

# Logs
docker-compose logs -f wordpress

# WP-CLI
docker exec -it wordpress_cli wp --info
```

---

## All Paths (Quick Copy)

```
/Users/tomrobak/_code_/fchub
/Users/tomrobak/_code_/fluentcommunity-vibe
/Users/tomrobak/_code_/fchub-plugins/fchub-chat
/Users/tomrobak/_code_/fchub-plugins/fchub-stream
/Users/tomrobak/_code_/fchub-plugins/fchub-stream-public
/Users/tomrobak/_code_/fchub-plugins/fchub-companion
/Users/tomrobak/_code_/fluentcommunity-vibe/fluent-community-mobile
/Users/tomrobak/_code_/fchub-licenses-sdks
/Users/tomrobak/_code_/fchub-plugins/fchub-docs
```
