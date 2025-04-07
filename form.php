<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal Linking Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea {
            height: 150px;
            resize: vertical;
        }
        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            border-radius: 4px;
            font-size: 16px;
        }
        button:hover {
            background-color: #2980b9;
        }
        .results {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #f9f9f9;
        }
        .loading {
            text-align: center;
            padding: 20px;
            display: none;
        }
        .tab {
            overflow: hidden;
            border: 1px solid #ccc;
            background-color: #f1f1f1;
            margin-bottom: 20px;
        }
        .tab button {
            background-color: inherit;
            float: left;
            border: none;
            outline: none;
            cursor: pointer;
            padding: 14px 16px;
            transition: 0.3s;
            color: #333;
        }
        .tab button:hover {
            background-color: #ddd;
        }
        .tab button.active {
            background-color: #3498db;
            color: white;
        }
        .tabcontent {
            display: none;
            padding: 6px 12px;
            border: 1px solid #ccc;
            border-top: none;
        }
        #errorMessage {
            color: red;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .candidate-item {
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .candidate-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        pre {
            white-space: pre-wrap;
            background-color: #f1f1f1;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Internal Linking Tool</h1>
        
        <div class="tab">
            <button class="tablinks active" onclick="openTab(event, 'basicTab')">Basic Search</button>
            <button class="tablinks" onclick="openTab(event, 'advancedTab')">Advanced Options</button>
        </div>
        
        <div id="basicTab" class="tabcontent" style="display: block;">
            <div id="errorMessage"></div>
            
            <form  id="linkingForm">
                <div>
                    <label for="domain">Domain (without http/https):</label>
                    <input type="text" id="domain" name="domain" placeholder="example.com" required>
                </div>
                
                <div>
                    <label for="keyword">Keyword or Phrase:</label>
                    <input type="text" id="keyword" name="keyword" placeholder="best product review" required>
                </div>
                
                <div>
                    <label for="targetUrl">Target URL (page to link to):</label>
                    <input type="text" id="targetUrl" name="targetUrl" placeholder="https://example.com/target-page" required>
                </div>
                
                <button type="submit">Find Linking Opportunities</button>
            </form>
        </div>
        
        <div id="advancedTab" class="tabcontent">
            <h3>Custom GPT Prompt</h3>
            <p>Customize the instructions sent to GPT for analyzing internal linking opportunities:</p>
            
            <form id="customPromptForm">
                <div>
                    <label for="customPrompt">Custom GPT Prompt:</label>
                    <textarea id="customPrompt" name="customPrompt">Analyze the following data for internal linking opportunities:

Domain: {domain}
Keyword: {keyword}
Target URL to link to: {targetUrl}

Pages that don't already link to the target URL (good candidates for linking):
{candidates}

For each candidate page above, please provide:
1. URL of the candidate page
2. Title of the candidate page
1. Which existing sentences could be modified to include a link with the keyword '{keyword}'.
2. Suggested new content/paragraphs to add that would make it natural to include the link.
3. 2-3 variations of anchor text for each linking opportunity.
4. Rate each opportunity from 1-10 based on relevance and naturalness.

Format your response in a clear, structured way that can be easily parsed.</textarea>
                </div>
                
                <button type="submit">Save Custom Prompt</button>
            </form>
        </div>
        
        <div class="loading" id="loadingIndicator">
            <p>Analyzing pages and generating recommendations...</p>
            <p>This may take a minute or two depending on the number of pages.</p>
        </div>
        
        <div class="results" id="resultsContainer" style="display: none;">
            <h2>Internal Linking Recommendations</h2>
            
            <div id="candidatePages">
                <h3>Candidate Pages</h3>
                <div id="candidateList"></div>
            </div>
            
            <div id="recommendations">
                <h3>GPT Recommendations</h3>
                <pre id="recommendationsContent"></pre>
            </div>
        </div>
    </div>

    <script>
        // Function to open tabs
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tabcontent");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }
            tablinks = document.getElementsByClassName("tablinks");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }
        
        // Store the custom prompt template
        let promptTemplate = document.getElementById('customPrompt').value;
        
        // Handle custom prompt form submission
        document.getElementById('customPromptForm').addEventListener('submit', function(e) {
            e.preventDefault();
            promptTemplate = document.getElementById('customPrompt').value;
            alert('Custom prompt template saved!');
        });
        
        // Handle main form submission
        document.getElementById('linkingForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const domain = document.getElementById('domain').value;
            const keyword = document.getElementById('keyword').value;
            const targetUrl = document.getElementById('targetUrl').value;
            
            if (!domain || !keyword || !targetUrl) {
                document.getElementById('errorMessage').textContent = 'All fields are required!';
                return;
            }
            
            document.getElementById('errorMessage').textContent = '';
            document.getElementById('loadingIndicator').style.display = 'block';
            document.getElementById('resultsContainer').style.display = 'none';
            
            // Make the API request
            fetch('gptworkflow.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    domain: domain,
                    keyword: keyword,
                    targetUrl: targetUrl,
                    customPrompt: promptTemplate
                })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingIndicator').style.display = 'none';
                document.getElementById('resultsContainer').style.display = 'block';
                
                if (data.status === 'success') {
                    // Display candidates
                    const candidateList = document.getElementById('candidateList');
                    candidateList.innerHTML = '';
                    
                    if (data.candidates && data.candidates.length > 0) {
                        data.candidates.forEach(candidate => {
                            const candidateEl = document.createElement('div');
                            candidateEl.className = 'candidate-item';
                            candidateEl.innerHTML = `
                                <div class="candidate-title">${candidate.title}</div>
                                <div><a href="${candidate.url}" target="_blank">${candidate.url}</a></div>
                                <div><strong>Snippet:</strong> ${candidate.snippet}</div>
                            `;
                            candidateList.appendChild(candidateEl);
                        });
                    } else {
                        candidateList.innerHTML = '<p>No candidate pages found.</p>';
                    }
                    
                    // Display recommendations
                    document.getElementById('recommendationsContent').textContent = data.recommendations;
                } else {
                    document.getElementById('resultsContainer').innerHTML = `
                        <h2>Error</h2>
                        <p>${data.message}</p>
                    `;
                }
            })
            .catch(error => {
                document.getElementById('loadingIndicator').style.display = 'none';
                document.getElementById('errorMessage').textContent = 'Error: ' + error.message;
            });
        });
    </script>
</body>
</html>