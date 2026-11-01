# ⚡ Quick Fix: Select Development Team

## The Error
"Signing for 'Muzic2App' requires a development team."

## Quick Solution (2 minutes)

### 1. Add Your Apple ID to Xcode
- Press `Cmd + ,` to open Settings
- Click **"Accounts"** tab
- Click **"+"** button → Select **"Apple ID"**
- Enter your Apple ID and password
- Click **"Sign In"**

### 2. Select Team in Project
- In Xcode, click the **blue project icon** (top of left sidebar)
- Select **"Muzic2App"** target (under TARGETS)
- Click **"Signing & Capabilities"** tab
- In **"Team"** dropdown, select your Apple ID
- ✅ You should see a green checkmark

### 3. Change Bundle ID (if needed)
- Still in Signing & Capabilities
- Change `com.muzic2.app` to `com.yourname.muzic2`
- Replace "yourname" with your name/username

### 4. Select Newer Simulator
- In top toolbar, click device selector
- Choose **iPhone 14** or **iPhone 15** (NOT iPhone 5)

### 5. Build and Run
- Press `Shift + Cmd + K` (Clean)
- Press `Cmd + R` (Run)

## ✅ Done!

Your app should now build and run. A free Apple ID is all you need for simulator testing.

---

**Note:** If you don't have an Apple ID, create one at appleid.apple.com (free).


