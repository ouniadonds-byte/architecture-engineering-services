<!--
  Projet  : Architecture & Engineering Services
  Auteur  : OUNIADON HAIROULAH (ID: 411030513)
  Fichier : assets/CREDITS.md
-->

# Crédits des médias (images et vidéo provisoires)

Ces médias sont **provisoires**. Ils seront remplacés par les rendus 3D et
les vidéos Lumion de l'auteur.

| Média | Source | Licence |
|---|---|---|
| `videos/hero-*` (extrait de 16 s, recompressé) | [Mixkit — « Modern house on the beach »](https://mixkit.co/free-stock-video/modern-house-on-the-beach-27543/) | Mixkit Stock Video Free License |
| `images/**/*.webp` | [Unsplash](https://unsplash.com) (photos d'architecture, recadrées et converties en WebP) | Unsplash License (usage gratuit, commercial autorisé) |

## Remplacer la vidéo par un export Lumion

```powershell
# 16 s, sans son, H.264 optimisé pour le web (~2 Mo)
ffmpeg -i rendu-lumion.mp4 -t 16 -an -c:v libx264 -preset slow -crf 26 -pix_fmt yuv420p -movflags +faststart hero-hd.mp4
ffmpeg -i rendu-lumion.mp4 -t 16 -an -c:v libvpx-vp9 -b:v 0 -crf 38 hero-hd.webm
ffmpeg -i rendu-lumion.mp4 -t 16 -an -vf scale=-2:480 -c:v libx264 -crf 28 -pix_fmt yuv420p -movflags +faststart hero-sd.mp4
ffmpeg -ss 2 -i rendu-lumion.mp4 -frames:v 1 -c:v libwebp -quality 78 hero-poster.webp
```
