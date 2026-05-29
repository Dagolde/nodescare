# HHMS Brand Assets

## Color Palette

| Name | Hex | Usage |
|---|---|---|
| Navy Blue | `#1A3A6B` | Primary brand color, backgrounds |
| Deep Navy | `#0D2347` | Dark variant, gradients |
| Medical Green | `#00B894` | Accent, cross, highlights |
| Bright Teal | `#00D4A8` | Gradient start for cross |
| White | `#FFFFFF` | Text on dark backgrounds |

## Files

| File | Purpose |
|---|---|
| `logo-full.svg` | Full horizontal lockup (icon + wordmark). Use in web headers, documents |
| `logo-icon.svg` | Standalone icon mark. Use for app icons, favicons, avatars |
| `logo-white.svg` | White variant for dark backgrounds (navbar, splash screens) |

## Exporting to PNG / ICO

### Favicon (Web Admin)

1. Open `logo-icon.svg` at [realfavicongenerator.net](https://realfavicongenerator.net)
2. Download the package and place files in `backend/public/`:
   - `favicon.ico`
   - `favicon-16x16.png` → rename to `favicon-16.png`
   - `favicon-32x32.png` → rename to `favicon-32.png`
   - `apple-touch-icon.png`
   - `android-chrome-192x192.png` → rename to `icon-192.png`
   - `android-chrome-512x512.png` → rename to `icon-512.png`

### Flutter App Icon

1. Export `mobile/assets/icons/app_icon.svg` as **1024×1024 PNG**:
   - **Inkscape CLI**: `inkscape app_icon.svg --export-png=app_icon.png --export-width=1024`
   - **Browser**: Open SVG → right-click → screenshot, or use [svgtopng.com](https://svgtopng.com)
   - Save as `mobile/assets/icons/app_icon.png`

2. Export `mobile/assets/icons/app_icon_foreground.svg` as **1024×1024 PNG**:
   - Save as `mobile/assets/icons/app_icon_foreground.png`

3. Run the launcher icons generator:
   ```bash
   cd mobile
   flutter pub get
   flutter pub run flutter_launcher_icons
   ```
   This generates all required sizes for Android (`mipmap-*`) and iOS (`AppIcon.appiconset`).

### Android Manual Sizes (if not using flutter_launcher_icons)

| Density | Size | Path |
|---|---|---|
| mdpi | 48×48 | `android/app/src/main/res/mipmap-mdpi/ic_launcher.png` |
| hdpi | 72×72 | `android/app/src/main/res/mipmap-hdpi/ic_launcher.png` |
| xhdpi | 96×96 | `android/app/src/main/res/mipmap-xhdpi/ic_launcher.png` |
| xxhdpi | 144×144 | `android/app/src/main/res/mipmap-xxhdpi/ic_launcher.png` |
| xxxhdpi | 192×192 | `android/app/src/main/res/mipmap-xxxhdpi/ic_launcher.png` |

### iOS Manual Sizes (if not using flutter_launcher_icons)

| Size | Usage |
|---|---|
| 20×20 | Notification |
| 29×29 | Settings |
| 40×40 | Spotlight |
| 60×60 | iPhone app |
| 76×76 | iPad app |
| 83.5×83.5 | iPad Pro |
| 1024×1024 | App Store |

## Usage Rules

- Always maintain clear space equal to the height of the "H" letter around the logo
- Do not stretch, rotate, or recolor the logo
- On light backgrounds: use `logo-full.svg` (dark version)
- On dark/colored backgrounds: use `logo-white.svg`
- Minimum size: 80px wide for the full lockup, 24px for the icon mark
