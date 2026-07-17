import fs from 'fs';
import path from 'path';
import sharp from 'sharp';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const publicDir = path.resolve(__dirname, '../public');

// Исключаем системные иконки, PWA и фавиконы (чтобы не сломать манифесты и iOS-кэш)
const skipKeywords = ['icon', 'pwa', 'favicon', 'apple-touch'];

function shouldSkip(filename) {
    const lower = filename.toLowerCase();
    return skipKeywords.some(keyword => lower.includes(keyword));
}

async function convertDirectory(dir) {
    const entries = fs.readdirSync(dir, { withFileTypes: true });

    let savedBytes = 0;
    let convertedCount = 0;

    for (const entry of entries) {
        const fullPath = path.join(dir, entry.name);

        if (entry.isDirectory()) {
            const result = await convertDirectory(fullPath);
            savedBytes += result.savedBytes;
            convertedCount += result.convertedCount;
        } else if (entry.isFile()) {
            const ext = path.extname(entry.name).toLowerCase();
            
            if (['.png', '.jpg', '.jpeg'].includes(ext)) {
                if (shouldSkip(entry.name)) {
                    console.log(`Skipping: ${entry.name} (matches skip keywords)`);
                    continue;
                }

                const newPath = fullPath.substring(0, fullPath.lastIndexOf('.')) + '.webp';
                
                try {
                    const originalSize = fs.statSync(fullPath).size;
                    
                    await sharp(fullPath)
                        .webp({ quality: 85 })
                        .toFile(newPath);
                        
                    const newSize = fs.statSync(newPath).size;
                    const saved = originalSize - newSize;
                    
                    console.log(`Converted: ${entry.name} -> ${(saved / 1024).toFixed(2)} KB saved`);
                    
                    savedBytes += saved;
                    convertedCount++;
                    
                    // Удаляем оригинальный файл
                    fs.unlinkSync(fullPath);
                } catch (err) {
                    console.error(`Error converting ${entry.name}:`, err);
                }
            }
        }
    }
    
    return { savedBytes, convertedCount };
}

console.log('Starting WebP conversion...');
convertDirectory(publicDir).then(({ savedBytes, convertedCount }) => {
    console.log(`\nConversion complete!`);
    console.log(`Files converted: ${convertedCount}`);
    console.log(`Total space saved: ${(savedBytes / 1024 / 1024).toFixed(2)} MB`);
});
