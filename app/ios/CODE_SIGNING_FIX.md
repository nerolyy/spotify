# 🔧 Fix: Code Signing Error on iPhone Simulator

## Problem
"Muzic2App" failed to launch or exited before the debugger could attach. This is typically a code signing issue.

## ⚠️ Important Note About iPhone 5 Simulator
**iPhone 5 simulator is very old** (iOS 6-10 era) and may not be compatible with your app's iOS 14.0 deployment target. **Use a newer simulator** like:
- iPhone 14
- iPhone 15
- iPhone SE (3rd generation)

## ✅ Solution 1: Add Your Apple ID and Select Team (Step-by-Step)

### Step 1: Add Your Apple ID to Xcode

1. **Open Xcode Preferences:**
   - Xcode → Settings (or Preferences on older versions)
   - Or press `Cmd + ,` (Command + Comma)

2. **Go to Accounts tab:**
   - Click "Accounts" in the top toolbar

3. **Add your Apple ID:**
   - Click the "+" button at the bottom left
   - Select "Apple ID"
   - Enter your Apple ID email and password
   - Click "Sign In"
   - ✅ Your Apple ID will appear in the list

4. **Download certificates (if needed):**
   - Select your Apple ID in the list
   - Click "Download Manual Profiles" button (if available)
   - Wait for it to complete

5. **Close Preferences**

### Step 2: Select Team in Project Settings

1. **In Xcode, select the project** in the navigator (top item, blue icon)

2. **Select the "Muzic2App" target** in the center panel (under "TARGETS")

3. **Click "Signing & Capabilities" tab** at the top

4. **Configure signing:**
   - ✅ Make sure "Automatically manage signing" is CHECKED
   - Click the "Team" dropdown
   - You should now see your Apple ID/name
   - Select it from the list
   - If you see "(Personal Team)" or "(Free)", that's fine - it works for simulator

5. **If Team dropdown is empty or shows "None":**
   - Click "Add an Account..." in the dropdown
   - Sign in with your Apple ID
   - Go back to the Team dropdown and select your account

6. **Change Bundle Identifier** (recommended):
   - Change from `com.muzic2.app` to something unique like:
     - `com.yourname.muzic2`
     - `com.yourname.muzic2app`
   - Replace "yourname" with your actual name or username
   - This prevents conflicts with other apps

7. **Verify the settings:**
   - You should see a green checkmark ✅ next to "Signing Certificate"
   - If you see errors, Xcode will show what's wrong

### Step 3: Select a Compatible Simulator

1. **In the device selector** (top toolbar, next to the Play button):
   - Click the device dropdown
   - Choose a newer simulator:
     - **iPhone 14** (recommended)
     - **iPhone 15**
     - **iPhone SE (3rd generation)**
   - ⚠️ **DO NOT use iPhone 5** - it's too old for iOS 14.0

### Step 4: Clean and Build

1. **Clean the build folder:**
   - Product → Clean Build Folder
   - Or press `Shift + Cmd + K`

2. **Build the project:**
   - Product → Build
   - Or press `Cmd + B`
   - Wait for it to complete successfully

3. **Run the app:**
   - Product → Run
   - Or press `Cmd + R`
   - The app should launch on the simulator

## ✅ Solution 2: Reset Simulator

If the issue persists:

1. **Reset the simulator:**
   - In Xcode: Window → Devices and Simulators
   - Select your simulator
   - Right-click → "Erase All Content and Settings"
   - Confirm

2. **Or delete and recreate:**
   - Devices and Simulators → Simulators tab
   - Delete the iPhone 5 simulator
   - Click "+" to add a new simulator
   - Choose iPhone 14 or newer with iOS 16+

## ✅ Solution 3: Manual Project File Fix

If Xcode UI doesn't work, the project file has been updated to remove the hardcoded team. You still need to:

1. Open Xcode
2. Set your Team in Signing & Capabilities
3. Use a newer simulator

## 🔍 Troubleshooting

### Error: "No signing certificate found"
- Go to Xcode → Preferences → Accounts
- Add your Apple ID
- Select your account → Download Manual Profiles
- Go back to Signing & Capabilities and select your team

### Error: "Bundle identifier already in use"
- Change the Bundle Identifier to something unique
- Use format: `com.yourname.muzic2`

### App still won't launch
1. **Check Console logs:**
   - Window → Devices and Simulators
   - Select your simulator
   - Click "Open Console"
   - Look for error messages

2. **Verify deployment target:**
   - Project → Target → General
   - Minimum Deployment should be iOS 14.0 or lower
   - iPhone 5 only supports up to iOS 10.3.1, so use a newer simulator

3. **Check Info.plist:**
   - Make sure all required keys are present
   - No missing or invalid entries

## 📱 Recommended Simulators

For iOS 14.0+ apps, use:
- **iPhone SE (3rd generation)** - iOS 15.0+
- **iPhone 14** - iOS 16.0+
- **iPhone 15** - iOS 17.0+

## ✅ Success Indicators

After fixing, you should see:
- ✅ Build succeeds without code signing errors
- ✅ App installs on simulator
- ✅ App launches and shows your content
- ✅ No errors in Xcode console

---

**Still having issues?** Check the Xcode console for specific error messages and share them for further troubleshooting.

