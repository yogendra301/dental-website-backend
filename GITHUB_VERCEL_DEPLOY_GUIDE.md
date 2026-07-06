# GitHub + Vercel Deployment Guide
### Dental Website — Static Demo Mode

---

## Part 1 — First-Time Setup (Do This Once)

### Step 1 — Create a GitHub Account (skip if you have one)
1. Go to https://github.com → Sign Up
2. Verify your email

---

### Step 2 — Install Git on Your Machine (if not installed)
```bash
# Check if git is installed
git --version

# If not installed on Ubuntu/Debian:
sudo apt install git -y

# Configure your identity (one time only)
git config --global user.name "Your Name"
git config --global user.email "your@email.com"
```

---

### Step 3 — Create a New GitHub Repository
1. Go to https://github.com/new
2. Repository name: dental-website-demo  (or any name)
3. Set visibility: Private (recommended — contains client config)
4. Do NOT tick "Add a README" or any other option
5. Click Create repository
6. Copy the repository URL shown on the next screen — looks like:
   https://github.com/YOUR_USERNAME/dental-website-demo.git

---

### Step 4 — Create a .gitignore File
This prevents pushing backend files, secrets, backups, and node_modules.

Run these commands from your project root (/home/abc/dental-website):

```bash
cd /home/abc/dental-website

cat > .gitignore << 'EOF'
# Backend — not needed for Vercel static demo
backend/
node_modules/

# Environment / secrets
.env
.env.*
!.env.example

# ZIP backups
*.zip

# OS / editor junk
.DS_Store
Thumbs.db
.vscode/
.idea/
.gemini/

# Logs
*.log
npm-debug.log*
EOF
```

---

### Step 5 — Enable Demo Mode in client-config.js

Before pushing, turn on demo mode so Vercel shows the working demo:

Open frontend/js/client-config.js and change:
    demoMode: false,
to:
    demoMode: true,  // Vercel demo — uses localStorage mock API

IMPORTANT: When you want to run locally with the real backend again, flip this back to false.

---

### Step 6 — Initialize Git and Push to GitHub

Run these commands from your project root:

```bash
cd /home/abc/dental-website

# Initialize a git repo in this folder
git init

# Tell git which branch to use (main is standard)
git branch -M main

# Connect this folder to your GitHub repo  <-- REPLACE with your actual URL
git remote add origin https://github.com/YOUR_USERNAME/dental-website-demo.git

# Stage all files (gitignore will automatically exclude backend/, *.zip, etc.)
git add .

# Check what will be committed — review this output
git status

# Make your first commit
git commit -m "Initial commit — static demo site"

# Push to GitHub
git push -u origin main
```

You will be prompted for your GitHub username and password.
GitHub no longer accepts plain passwords — use a Personal Access Token instead.

HOW TO CREATE A GITHUB PERSONAL ACCESS TOKEN:
1. GitHub → Settings (top-right avatar) → Developer Settings → Personal Access Tokens → Tokens (classic)
2. Click Generate new token (classic)
3. Name it anything (e.g. dental-deploy)
4. Tick "repo" scope
5. Click Generate → Copy the token immediately (you won't see it again)
6. When git asks for password — paste this token

---

### Step 7 — Deploy on Vercel
1. Go to https://vercel.com → Sign Up / Log In (use GitHub to sign in for easiest setup)
2. Click Add New → Project
3. Under "Import Git Repository" → select dental-website-demo
4. Vercel will auto-detect the vercel.json in the project root
5. Framework Preset: select Other (or it will auto-detect — no framework needed)
6. Root Directory: leave blank (vercel.json handles output directory as frontend/)
7. Click Deploy

Vercel will give you a live URL like: https://dental-website-demo.vercel.app

---

## Part 2 — Recurring Workflow (Every Time You Update the Demo)

### Scenario A — You Made Code Changes and Want to Push

```bash
cd /home/abc/dental-website

# See what changed
git status

# Stage all your changes
git add .

# Commit with a descriptive message
git commit -m "Fix: service card images updated"

# Push to GitHub (Vercel auto-deploys within ~30 seconds)
git push
```

That is it. Vercel picks up the push and redeploys automatically.

---

### Scenario B — Switching Between Demo Mode and Real Backend

To enable demo mode (for Vercel / client):
    demoMode: true,

To switch back to real backend (for local dev):
    demoMode: false,

Tip: Keep two branches — main (demo mode on) and dev (real backend).
Vercel deploys from main. You work on dev and merge when ready.

---

### Scenario C — Two Branches (Clean Separation — Recommended)

One-time branch setup:
```bash
# Create a dev branch for local development (backend ON)
git checkout -b dev

# Set demoMode: false in client-config.js for dev work
# ... make changes ...
git add .
git commit -m "Working on X feature"
git push origin dev
```

When ready to update the Vercel demo:
```bash
# Switch to main (demo mode ON)
git checkout main

# Merge changes from dev
git merge dev

# Make sure demoMode is true in client-config.js
# Fix it if needed, then:
git add frontend/js/client-config.js
git commit -m "Enable demo mode for Vercel"

# Push — Vercel redeploys automatically
git push origin main

# Go back to dev work
git checkout dev
```

---

## Part 3 — Quick Reference Cheat Sheet

| Task                           | Command                            |
|--------------------------------|------------------------------------|
| See changed files              | git status                         |
| Stage all changes              | git add .                          |
| Stage one file                 | git add frontend/js/app.js         |
| Commit                         | git commit -m "your message"       |
| Push to GitHub                 | git push                           |
| Pull latest from GitHub        | git pull                           |
| Switch to dev branch           | git checkout dev                   |
| Switch to main branch          | git checkout main                  |
| See commit history             | git log --oneline -10              |
| Undo last commit (keep changes)| git reset --soft HEAD~1            |

---

## Part 4 — Vercel Dashboard Reference

| Task                  | Where                                              |
|-----------------------|----------------------------------------------------|
| See deploy status     | vercel.com → Project → Deployments tab             |
| Get live URL          | vercel.com → Project → top of page                 |
| Redeploy manually     | Deployments tab → 3-dot menu → Redeploy            |
| Custom domain         | Project → Settings → Domains → Add                 |
| See deploy logs       | Click any deployment → View Function Logs           |

---

NOTE: It is fine to commit demoMode: true to the main branch.
The backend code is excluded via .gitignore so there is no risk of leaking server logic or DB credentials.
