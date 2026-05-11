import re
import os

file_path = r'c:\Users\user\Desktop\⠀⠀\LARPLARP\Diversity.sql'
temp_path = file_path + '.tmp'

pattern = re.compile(r", ('[^']*'|NULL), ('[^']*'|NULL), ('[^']*'|NULL), ('[^']*'|NULL)(\)\s*[,;]\s*)$")

with open(file_path, 'r', encoding='utf-8') as f, open(temp_path, 'w', encoding='utf-8') as out:
    in_insert = False
    for line in f:
        if 'INSERT INTO `job_offer_applications`' in line:
            in_insert = True
            out.write(line)
            continue
        
        if in_insert:
            if line.strip().startswith('('):
                # Try to replace
                new_line = pattern.sub(r", \1, \3, \4\5", line)
                out.write(new_line)
                if line.strip().endswith(';'):
                    in_insert = False
            elif line.strip() == '' or line.strip().startswith('--'):
                out.write(line)
            else:
                in_insert = False
                out.write(line)
        else:
            out.write(line)

os.replace(temp_path, file_path)
print("Cleanup complete.")
