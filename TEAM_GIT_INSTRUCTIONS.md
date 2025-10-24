# Git Instructions for Team Members

## 🚀 Initial Setup (First Time Only)

### Step 1: Clone the Repository
```bash
git clone https://github.com/deGarushiya/PARTS_Treasury.git
cd PARTS_Treasury
```

### Step 2: Switch to Your Branch

**For Assessment Posting team:**
```bash
git checkout feature/assessment-posting
```

**For Manual Debit team:**
```bash
git checkout feature/manual-debit
```

If you get an error "branch does not exist", run this first:
```bash
git fetch origin
git checkout feature/assessment-posting
```

---

## 💻 Daily Work Routine

### Before you start working:
```bash
# Make sure you're on your branch
git branch

# Pull latest changes
git pull origin feature/assessment-posting
```

### After you finish working:
```bash
# 1. Check what you changed
git status

# 2. Add all your changes
git add .

# 3. Commit with a message describing what you did
git commit -m "Add assessment form UI"

# 4. Push to GitHub
git push origin feature/assessment-posting
```

---

## 📝 Commit Message Examples

Good commit messages:
- ✅ "Add assessment posting form UI"
- ✅ "Implement search functionality"
- ✅ "Fix table styling issues"
- ✅ "Connect to backend API"

Bad commit messages:
- ❌ "update"
- ❌ "changes"
- ❌ "asdf"

---

## ⚠️ Common Errors

### Error: "src refspec feature/assessment-posting does not match any"
**Solution:**
```bash
git fetch origin
git checkout feature/assessment-posting
```

### Error: "Your branch is behind"
**Solution:**
```bash
git pull origin feature/assessment-posting
```

### Error: "You have uncommitted changes"
**Solution:**
```bash
git add .
git commit -m "Save my work"
git push origin feature/assessment-posting
```

---

## 🆘 Need Help?

Contact the main developer (you) if:
- You get merge conflicts
- You accidentally deleted something
- You're not sure what branch you're on
- Any other Git-related issues

---

## 📌 Your Branch

**Assessment Posting Team:** `feature/assessment-posting`
**Manual Debit Team:** `feature/manual-debit`

**Always make sure you're on YOUR branch before making changes!**

