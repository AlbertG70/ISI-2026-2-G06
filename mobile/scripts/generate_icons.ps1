Add-Type -AssemblyName System.Drawing

$srcPath = "C:/Users/Vss/.gemini/antigravity/brain/614952ce-3621-4282-93b1-fcf7b246d36f/.user_uploaded/media_1794347555387_d8d5d7ed.jpg"
if (-not (Test-Path $srcPath)) {
    Write-Error "Source image not found: $srcPath"
    exit 1
}

$repoRoot = "C:/Users/Vss/ISI-2026-2-G06"

$csharp = @"
using System;
using System.IO;
using System.Drawing;
using System.Drawing.Drawing2D;
using System.Drawing.Imaging;

public class MobileIconEngine {
    public static void GenerateSolidSquare(string srcPath, string destPath, int size, double scale, int r, int g, int b) {
        Directory.CreateDirectory(Path.GetDirectoryName(destPath));
        Color bgColor = Color.FromArgb(r, g, b);
        using (var src = (Bitmap)Image.FromFile(srcPath))
        using (var dest = new Bitmap(size, size, PixelFormat.Format24bppRgb))
        using (var gfx = Graphics.FromImage(dest)) {
            gfx.InterpolationMode = InterpolationMode.HighQualityBicubic;
            gfx.SmoothingMode = SmoothingMode.HighQuality;
            gfx.PixelOffsetMode = PixelOffsetMode.HighQuality;
            gfx.Clear(bgColor);

            int newH = (int)(size * scale);
            int newW = (int)(src.Width * ((double)newH / src.Height));
            int posX = (size - newW) / 2;
            int posY = (size - newH) / 2;

            gfx.DrawImage(src, posX, posY, newW, newH);
            dest.Save(destPath, ImageFormat.Png);
        }
    }

    public static void GenerateRound(string srcPath, string destPath, int size, double scale, int r, int g, int b) {
        Directory.CreateDirectory(Path.GetDirectoryName(destPath));
        Color bgColor = Color.FromArgb(r, g, b);
        using (var src = (Bitmap)Image.FromFile(srcPath))
        using (var dest = new Bitmap(size, size, PixelFormat.Format32bppArgb))
        using (var gfx = Graphics.FromImage(dest)) {
            gfx.InterpolationMode = InterpolationMode.HighQualityBicubic;
            gfx.SmoothingMode = SmoothingMode.HighQuality;
            gfx.PixelOffsetMode = PixelOffsetMode.HighQuality;
            gfx.Clear(Color.Transparent);

            using (var path = new GraphicsPath()) {
                path.AddEllipse(0, 0, size, size);
                gfx.SetClip(path);
                gfx.Clear(bgColor);

                int newH = (int)(size * scale);
                int newW = (int)(src.Width * ((double)newH / src.Height));
                int posX = (size - newW) / 2;
                int posY = (size - newH) / 2;

                gfx.DrawImage(src, posX, posY, newW, newH);
            }
            dest.Save(destPath, ImageFormat.Png);
        }
    }

    public static void GenerateTransparentForeground(string srcPath, string destPath, int canvasSize, int safeContentSize) {
        Directory.CreateDirectory(Path.GetDirectoryName(destPath));
        using (var src = (Bitmap)Image.FromFile(srcPath))
        using (var fgRaw = new Bitmap(src.Width, src.Height, PixelFormat.Format32bppArgb)) {
            var rect = new Rectangle(0, 0, src.Width, src.Height);
            var srcData = src.LockBits(rect, ImageLockMode.ReadOnly, PixelFormat.Format24bppRgb);
            var fgData = fgRaw.LockBits(rect, ImageLockMode.WriteOnly, PixelFormat.Format32bppArgb);

            int srcBytesCount = srcData.Stride * src.Height;
            int fgBytesCount = fgData.Stride * src.Height;
            byte[] srcBytes = new byte[srcBytesCount];
            byte[] fgBytes = new byte[fgBytesCount];

            System.Runtime.InteropServices.Marshal.Copy(srcData.Scan0, srcBytes, 0, srcBytesCount);

            double tLow = 18.0;
            double tHigh = 75.0;

            for (int y = 0; y < src.Height; y++) {
                int srcRow = y * srcData.Stride;
                int fgRow = y * fgData.Stride;
                for (int x = 0; x < src.Width; x++) {
                    int si = srcRow + (x * 3);
                    int fi = fgRow + (x * 4);

                    byte b = srcBytes[si];
                    byte g = srcBytes[si + 1];
                    byte r = srcBytes[si + 2];

                    int maxC = Math.Max(r, Math.Max(g, b));

                    if (maxC <= tLow) {
                        fgBytes[fi] = 0;
                        fgBytes[fi + 1] = 0;
                        fgBytes[fi + 2] = 0;
                        fgBytes[fi + 3] = 0;
                    } else {
                        int alpha = (maxC >= tHigh) ? 255 : (int)(255.0 * (maxC - tLow) / (tHigh - tLow));
                        fgBytes[fi] = 255;
                        fgBytes[fi + 1] = 255;
                        fgBytes[fi + 2] = 255;
                        fgBytes[fi + 3] = (byte)alpha;
                    }
                }
            }

            System.Runtime.InteropServices.Marshal.Copy(fgBytes, 0, fgData.Scan0, fgBytesCount);
            src.UnlockBits(srcData);
            fgRaw.UnlockBits(fgData);

            using (var canvas = new Bitmap(canvasSize, canvasSize, PixelFormat.Format32bppArgb))
            using (var gfx = Graphics.FromImage(canvas)) {
                gfx.InterpolationMode = InterpolationMode.HighQualityBicubic;
                gfx.SmoothingMode = SmoothingMode.HighQuality;
                gfx.PixelOffsetMode = PixelOffsetMode.HighQuality;
                gfx.Clear(Color.Transparent);

                double scale = (double)safeContentSize / Math.Max(fgRaw.Width, fgRaw.Height);
                int w = (int)(fgRaw.Width * scale);
                int h = (int)(fgRaw.Height * scale);
                int x = (canvasSize - w) / 2;
                int y = (canvasSize - h) / 2;

                gfx.DrawImage(fgRaw, x, y, w, h);
                canvas.Save(destPath, ImageFormat.Png);
            }
        }
    }

    public static void GenerateSplashScreen(string srcPath, string destPath, int width, int height, double logoFraction) {
        Directory.CreateDirectory(Path.GetDirectoryName(destPath));
        using (var src = (Bitmap)Image.FromFile(srcPath))
        using (var dest = new Bitmap(width, height, PixelFormat.Format24bppRgb))
        using (var gfx = Graphics.FromImage(dest)) {
            gfx.InterpolationMode = InterpolationMode.HighQualityBicubic;
            gfx.SmoothingMode = SmoothingMode.HighQuality;
            gfx.PixelOffsetMode = PixelOffsetMode.HighQuality;
            gfx.Clear(Color.Black);

            int minDim = Math.Min(width, height);
            int logoH = (int)(minDim * logoFraction);
            int logoW = (int)(src.Width * ((double)logoH / src.Height));
            int posX = (width - logoW) / 2;
            int posY = (height - logoH) / 2;

            gfx.DrawImage(src, posX, posY, logoW, logoH);
            dest.Save(destPath, ImageFormat.Png);
        }
    }

    public static void GenerateSolidBackground(string destPath, int size, int r, int g, int b) {
        Directory.CreateDirectory(Path.GetDirectoryName(destPath));
        Color bgColor = Color.FromArgb(r, g, b);
        using (var dest = new Bitmap(size, size, PixelFormat.Format24bppRgb))
        using (var gfx = Graphics.FromImage(dest)) {
            gfx.Clear(bgColor);
            dest.Save(destPath, ImageFormat.Png);
        }
    }
}
"@

Add-Type -TypeDefinition $csharp -ReferencedAssemblies "System.Drawing.dll"

Write-Host "=== 1. Generating mobile-expo assets ==="
# Expo root icon (1024x1024, universal for iOS & store)
[MobileIconEngine]::GenerateSolidSquare($srcPath, "$repoRoot/mobile-expo/assets/icon.png", 1024, 0.84, 0, 0, 0)

# Expo Android adaptive icon foreground (512x512, transparent, safe zone 340px)
[MobileIconEngine]::GenerateTransparentForeground($srcPath, "$repoRoot/mobile-expo/assets/android-icon-foreground.png", 512, 335)

# Expo Android adaptive icon background (512x512, black #000000)
[MobileIconEngine]::GenerateSolidSquare($srcPath, "$repoRoot/mobile-expo/assets/android-icon-background.png", 512, 1.0, 0, 0, 0)

# Expo Android monochrome themed icon (432x432, transparent, safe zone 280px)
[MobileIconEngine]::GenerateTransparentForeground($srcPath, "$repoRoot/mobile-expo/assets/android-icon-monochrome.png", 432, 280)

# Expo splash screen icon (1024x1024, centered rune with transparency)
[MobileIconEngine]::GenerateTransparentForeground($srcPath, "$repoRoot/mobile-expo/assets/splash-icon.png", 1024, 600)

# Expo web favicon (48x48)
[MobileIconEngine]::GenerateSolidSquare($srcPath, "$repoRoot/mobile-expo/assets/favicon.png", 48, 0.88, 0, 0, 0)

Write-Host "=== 2. Generating mobile/android mipmap icons ==="
$androidRes = "$repoRoot/mobile/android/app/src/main/res"

$densities = @(
    @{ Name = "mdpi"; LauncherSize = 48; FgCanvas = 108; FgSafe = 70 }
    @{ Name = "hdpi"; LauncherSize = 72; FgCanvas = 162; FgSafe = 106 }
    @{ Name = "xhdpi"; LauncherSize = 96; FgCanvas = 216; FgSafe = 142 }
    @{ Name = "xxhdpi"; LauncherSize = 144; FgCanvas = 324; FgSafe = 212 }
    @{ Name = "xxxhdpi"; LauncherSize = 192; FgCanvas = 432; FgSafe = 284 }
)

foreach ($d in $densities) {
    $folder = "$androidRes/mipmap-$($d.Name)"
    Write-Host "  Processing $folder..."
    
    # Legacy launcher
    [MobileIconEngine]::GenerateSolidSquare($srcPath, "$folder/ic_launcher.png", $d.LauncherSize, 0.84, 0, 0, 0)
    
    # Round launcher
    [MobileIconEngine]::GenerateRound($srcPath, "$folder/ic_launcher_round.png", $d.LauncherSize, 0.82, 0, 0, 0)
    
    # Adaptive foreground
    [MobileIconEngine]::GenerateTransparentForeground($srcPath, "$folder/ic_launcher_foreground.png", $d.FgCanvas, $d.FgSafe)
}

Write-Host "=== 3. Generating mobile/android splash screens ==="
# Standard splashes
[MobileIconEngine]::GenerateSplashScreen($srcPath, "$androidRes/drawable/splash.png", 480, 320, 0.45)
[MobileIconEngine]::GenerateSplashScreen($srcPath, "$androidRes/drawable-port-mdpi/splash.png", 320, 480, 0.45)
[MobileIconEngine]::GenerateSplashScreen($srcPath, "$androidRes/drawable-port-hdpi/splash.png", 480, 800, 0.45)
[MobileIconEngine]::GenerateSplashScreen($srcPath, "$androidRes/drawable-port-xhdpi/splash.png", 720, 1280, 0.45)
[MobileIconEngine]::GenerateSplashScreen($srcPath, "$androidRes/drawable-port-xxhdpi/splash.png", 960, 1600, 0.45)
[MobileIconEngine]::GenerateSplashScreen($srcPath, "$androidRes/drawable-port-xxxhdpi/splash.png", 1280, 1920, 0.45)

[MobileIconEngine]::GenerateSplashScreen($srcPath, "$androidRes/drawable-land-mdpi/splash.png", 480, 320, 0.45)
[MobileIconEngine]::GenerateSplashScreen($srcPath, "$androidRes/drawable-land-hdpi/splash.png", 800, 480, 0.45)
[MobileIconEngine]::GenerateSplashScreen($srcPath, "$androidRes/drawable-land-xhdpi/splash.png", 1280, 720, 0.45)
[MobileIconEngine]::GenerateSplashScreen($srcPath, "$androidRes/drawable-land-xxhdpi/splash.png", 1600, 960, 0.45)
[MobileIconEngine]::GenerateSplashScreen($srcPath, "$androidRes/drawable-land-xxxhdpi/splash.png", 1920, 1280, 0.45)

Write-Host "=== 4. Generating iOS AppIcon.appiconset in mobile/ios and docs/app-icons ==="
$iosCatalogs = @(
    "$repoRoot/mobile/ios/App/App/Assets.xcassets/AppIcon.appiconset",
    "$repoRoot/docs/app-icons/ios/AppIcon.appiconset"
)

$iosSizes = @(
    @{ Name = "AppIcon-1024x1024@1x.png"; Size = 1024 }
    @{ Name = "AppIcon-60x60@3x.png"; Size = 180 }
    @{ Name = "AppIcon-60x60@2x.png"; Size = 120 }
    @{ Name = "AppIcon-83.5x83.5@2x.png"; Size = 167 }
    @{ Name = "AppIcon-76x76@2x.png"; Size = 152 }
    @{ Name = "AppIcon-76x76@1x.png"; Size = 76 }
    @{ Name = "AppIcon-40x40@3x.png"; Size = 120 }
    @{ Name = "AppIcon-40x40@2x.png"; Size = 80 }
    @{ Name = "AppIcon-40x40@1x.png"; Size = 40 }
    @{ Name = "AppIcon-29x29@3x.png"; Size = 87 }
    @{ Name = "AppIcon-29x29@2x.png"; Size = 58 }
    @{ Name = "AppIcon-29x29@1x.png"; Size = 29 }
    @{ Name = "AppIcon-20x20@3x.png"; Size = 60 }
    @{ Name = "AppIcon-20x20@2x.png"; Size = 40 }
    @{ Name = "AppIcon-20x20@1x.png"; Size = 20 }
)

foreach ($catalog in $iosCatalogs) {
    foreach ($item in $iosSizes) {
        $out = "$catalog/$($item.Name)"
        [MobileIconEngine]::GenerateSolidSquare($srcPath, $out, $item.Size, 0.84, 0, 0, 0)
    }
}

# Also export standalone packages in docs/app-icons/
Write-Host "=== 5. Exporting standalone packages in docs/app-icons ==="
[MobileIconEngine]::GenerateSolidSquare($srcPath, "$repoRoot/docs/app-icons/android/play-store-512.png", 512, 0.84, 0, 0, 0)
[MobileIconEngine]::GenerateSolidSquare($srcPath, "$repoRoot/docs/app-icons/ios/app-store-1024.png", 1024, 0.84, 0, 0, 0)

Write-Host "All icons generated successfully!"
