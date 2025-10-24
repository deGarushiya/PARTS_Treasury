# Treasury Module Integration Guide

## 🏗️ System Architecture

The Treasury module is built as a **decoupled system**:
- **Backend**: Laravel REST API (Port 8000)
- **Frontend**: React SPA (Port 3000)

---

## 🚀 How to Run

### Windows (Easy Way):
Double-click `start-dev.bat` - it will start both backend and frontend automatically!

### Manual Way:

**Terminal 1 - Backend:**
```bash
php artisan serve
```

**Terminal 2 - Frontend:**
```bash
cd main
npm start
```

---

## 🔧 First Time Setup

### 1. Install Dependencies

**Backend:**
```bash
composer install
```

**Frontend:**
```bash
cd main
npm install
```

### 2. Configure Environment

1. Copy `.env.example` to `.env`
2. Update database credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=alcala
DB_USERNAME=root
DB_PASSWORD=your_password_here
```

### 3. Run Migrations (if needed)
```bash
php artisan migrate
```

---

## 🔗 Integration Options

### Option 1: API Integration (Recommended)
**Best for:** Existing system that wants to use Treasury features

Your system calls our REST APIs:
```javascript
// Example: Get penalty records
fetch('http://localhost:8000/api/penalty?barangay=MACAYO')
  .then(res => res.json())
  .then(data => console.log(data));
```

**Available APIs:**
- `/api/barangays` - Get all barangays
- `/api/owners/search` - Search property owners
- `/api/properties/search` - Search properties
- `/api/tax-due` - Get tax due information
- `/api/penalty` - Get penalty records
- `/api/penalty/post` - Post penalties

### Option 2: Iframe Embed
**Best for:** Quick integration without code changes

Embed our frontend in your system:
```html
<iframe 
  src="http://localhost:3000/penalty-posting" 
  width="100%" 
  height="800px"
  frameborder="0">
</iframe>
```

### Option 3: Full Merge
**Best for:** Unified application

Steps:
1. Move `main/src/*` to your `resources/js/` folder
2. Update import paths
3. Add routes to your main routing
4. Build with your Vite/Mix setup

**This requires significant refactoring!**

---

## 📂 Project Structure

```
parts-online/
├── app/                    # Laravel Controllers, Models
│   ├── Http/Controllers/Api/
│   │   ├── PenaltyPostingController.php
│   │   ├── TaxDueController.php
│   │   └── ...
│   └── Services/           # Business logic
├── main/                   # React Frontend
│   ├── src/
│   │   ├── pages/
│   │   │   ├── PenaltyPosting/
│   │   │   ├── PaymentPosting/
│   │   │   └── ManualDebit/
│   │   └── components/
│   └── package.json
├── routes/
│   └── api.php            # API endpoints
├── vendor/                # Composer dependencies
├── .env                   # Environment config
├── composer.json          # PHP dependencies
└── start-dev.bat          # Quick start script
```

---

## 🗄️ Database

The system expects these main tables:
- `taxpayer` - Property owners
- `property` - Properties
- `postingjournal` - Tax records
- `tpaccount` - Tax account transactions
- `t_barangay` - Barangay list
- `t_penaltyinterestparam` - Penalty rates

**Database name:** `alcala` (configurable in `.env`)

---

## 🆘 Common Issues

### Issue: "Connection refused"
**Solution:** Make sure both backend and frontend are running

### Issue: "CORS error"
**Solution:** Backend already configured for CORS in `config/cors.php`

### Issue: "Module not found"
**Solution:** 
- Backend: Run `composer install`
- Frontend: Run `cd main && npm install`

### Issue: "Port already in use"
**Solution:**
- Backend: Use different port: `php artisan serve --port=8001`
- Frontend: React will offer different port automatically

---

## 📞 Support

For integration questions, contact the Treasury development team.

**Main Developer:** [Your Name]
**Repository:** https://github.com/deGarushiya/PARTS_Treasury

