import { cpSync, existsSync, mkdirSync, rmSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const currentDirectory = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(currentDirectory, "..");
const sourceDirectory = resolve(projectRoot, "node_modules", "@bragamateus", "gui", "gui");
const targetDirectory = resolve(projectRoot, "assets", "vendor", "gui");

if (!existsSync(sourceDirectory)) {
  throw new Error("gUI runtime not found in node_modules. Run `npm install` first.");
}

rmSync(targetDirectory, { recursive: true, force: true });
mkdirSync(dirname(targetDirectory), { recursive: true });
cpSync(sourceDirectory, targetDirectory, { recursive: true });

console.log(`Synced gUI runtime to ${targetDirectory}`);
