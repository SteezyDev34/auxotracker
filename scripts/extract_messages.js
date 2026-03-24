const fs = require("fs");
const path = require("path");

const INPUT_FILE = path.resolve(__dirname, "../messages_filtrés.json");
const OUTPUT_FILE = path.resolve(__dirname, "../messages_parsed.json");

function readMessages(filePath) {
  const raw = fs.readFileSync(filePath, "utf8");
  return JSON.parse(raw);
}

function cleanLine(line) {
  if (!line) return "";
  let cleaned = line
    .replace(/\*\*/g, "")
    .replace(/[`']/g, "'")
    .replace(/"/g, "'")
    .trim();

  cleaned = cleaned.replace(/^[^A-Za-z0-9]+/, "").trim();
  return cleaned;
}

function extractDescription(lines, coteIndex) {
  for (let i = coteIndex - 1; i >= 0; i -= 1) {
    const line = cleanLine(lines[i]);
    if (!line) continue;
    const lower = line.toLowerCase();

    if (
      lower.includes("mise") ||
      lower.includes("objectif") ||
      lower.includes("pari disponible") ||
      lower.startsWith("http")
    ) {
      continue;
    }

    if (
      lower.includes("début du match") ||
      lower.includes("début des matchs")
    ) {
      continue;
    }

    return { text: line, index: i };
  }

  return { text: null, index: -1 };
}

function extractMatch(lines, startIndex) {
  if (startIndex < 0) {
    return null;
  }

  for (let i = startIndex - 1; i >= 0; i -= 1) {
    const line = cleanLine(lines[i]);
    if (!line) continue;
    const lower = line.toLowerCase();

    if (
      lower.includes("mise") ||
      lower.includes("objectif") ||
      lower.includes("pari disponible") ||
      lower.includes("confiance") ||
      lower.startsWith("http")
    ) {
      continue;
    }

    if (
      lower.includes("début du match") ||
      lower.includes("début des matchs")
    ) {
      continue;
    }

    return line;
  }

  return null;
}

function extractCote(text) {
  const match = text.match(/cote\s*:\s*([0-9]+[.,][0-9]+)/i);
  if (!match) {
    return null;
  }
  return parseFloat(match[1].replace(",", "."));
}

function stripMarkdown(value) {
  if (!value) return value;
  return value
    .replace(/\*\*/g, "")
    .replace(/__|~~|`/g, "")
    .trim();
}

function extractMessageInfo(entry) {
  const cote = extractCote(entry.text || "");
  const lines = entry.text ? entry.text.split(/\r?\n/) : [];

  let coteIndex = -1;
  for (let i = 0; i < lines.length; i += 1) {
    if (/cote\s*:/i.test(lines[i])) {
      coteIndex = i;
      break;
    }
  }

  let description = "";
  if (coteIndex > 0) {
    description = lines.slice(0, coteIndex).map(stripMarkdown).join(" ");
  } else if (coteIndex === 0) {
    description = "";
  } else {
    description = lines.map(stripMarkdown).join(" ");
  }

  let match = null;
  let descIndex = -1;
  if (coteIndex >= 0) {
    const descResult = extractDescription(lines, coteIndex);
    descIndex = descResult.index;
    match = extractMatch(lines, descIndex);
  }

  // Transformer la date au format dd/mm/aaaa
  let formattedDate = null;
  if (entry.date) {
    // Gère les formats ISO ou yyyy-mm-dd
    const matchDate = entry.date.match(/(\d{4})-(\d{2})-(\d{2})/);
    if (matchDate) {
      formattedDate = `${matchDate[3]}/${matchDate[2]}/${matchDate[1]}`;
    } else {
      formattedDate = entry.date;
    }
  }
  return {
    id: entry.id,
    date: formattedDate,
    match: stripMarkdown(match),
    description: description,
    cote,
  };
}

function run() {
  const messages = readMessages(INPUT_FILE);
  const parsed = messages.map(extractMessageInfo);
  fs.writeFileSync(OUTPUT_FILE, JSON.stringify(parsed, null, 2), "utf8");
  console.log(`Extraction terminée. Résultats enregistrés dans ${OUTPUT_FILE}`);
}

run();
