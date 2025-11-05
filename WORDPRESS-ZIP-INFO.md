# WordPress Plugin ZIP Structure

This document explains how our plugin ZIP is structured and why WordPress won't create duplicate plugins when you upload new versions.

## ZIP Structure

Our release ZIPs are structured correctly for WordPress:

```
fchub-stream-0.0.1.zip
└── fchub-stream/          ← Plugin folder (slug)
    ├── fchub-stream.php   ← Main plugin file
    ├── app/
    ├── boot/
    ├── config/
    ├── vendor/
    └── ...
```

## How WordPress Handles Plugin Updates

### First Installation

When you upload `fchub-stream-0.0.1.zip`:
1. WordPress extracts the ZIP
2. Finds the `fchub-stream/` folder inside
3. Copies it to `wp-content/plugins/fchub-stream/`
4. Plugin appears as "FCHub Stream" in Plugins list

### Subsequent Installations (Updates)

When you upload `fchub-stream-0.0.2.zip`:
1. WordPress extracts the ZIP
2. Finds the `fchub-stream/` folder inside
3. **Recognizes it matches existing `wp-content/plugins/fchub-stream/`**
4. **Deletes old files and replaces with new ones**
5. Plugin remains as "FCHub Stream" (no duplicate!)

### Why It Works

WordPress uses the **folder name** (`fchub-stream/`) as the plugin identifier, NOT the ZIP filename.

- ✅ `fchub-stream-0.0.1.zip` → extracts to `fchub-stream/`
- ✅ `fchub-stream-0.0.2.zip` → extracts to `fchub-stream/`
- ✅ `fchub-stream.zip` → extracts to `fchub-stream/`

All versions extract to the **same folder name**, so WordPress updates instead of duplicating.

## Our ZIP Files

We provide two ZIP files per release:

### 1. Versioned ZIP
- **Filename**: `fchub-stream-0.0.1.zip`, `fchub-stream-0.0.2.zip`, etc.
- **Use case**: Specific version tracking, rollback capability
- **Contents**: Identical structure with `fchub-stream/` folder

### 2. Latest ZIP
- **Filename**: `fchub-stream.zip` (no version)
- **Use case**: Always download newest version without checking release number
- **Contents**: Identical to versioned ZIP (just different filename)
- **URL**: `https://github.com/YOUR-USERNAME/fchub-stream-public/releases/latest/download/fchub-stream.zip`

Both files are **functionally identical** and will update the same plugin.

## Verification

Our build process verifies the ZIP structure:

```bash
# Local build
./build-release.sh 0.0.1
# ✓ ZIP structure verified: fchub-stream/ folder present
# ✓ WordPress will UPDATE existing plugin (not duplicate)

# GitHub Actions
# ✓ ZIP structure verified: fchub-stream/ folder present
```

## Common Issues

### ❌ Wrong Structure (Would Create Duplicates)

```
bad-plugin.zip
├── fchub-stream.php    ← Files directly in ZIP (BAD!)
├── app/
└── boot/
```

This would create `wp-content/plugins/fchub-stream.php` (file, not folder) and break everything.

### ✅ Correct Structure (Updates Properly)

```
good-plugin.zip
└── fchub-stream/       ← Folder in ZIP (GOOD!)
    ├── fchub-stream.php
    ├── app/
    └── boot/
```

This creates `wp-content/plugins/fchub-stream/` and updates properly.

## Testing

To verify your ZIP is correct:

```bash
# Check ZIP contents
unzip -l fchub-stream-0.0.1.zip | head -20

# Should show:
# fchub-stream/
# fchub-stream/fchub-stream.php
# fchub-stream/app/
# etc.
```

## References

- [WordPress Plugin Handbook - Plugin Basics](https://developer.wordpress.org/plugins/plugin-basics/)
- [WordPress Plugin Directory Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
