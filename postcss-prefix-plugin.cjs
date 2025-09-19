// PostCSS plugin to add 'mbx-' prefix to all CSS classes
const plugin = () => {
  return {
    postcssPlugin: 'postcss-prefix-mailbox',
    Rule(rule) {
      // Only process utility and component layers
      if (rule.parent && rule.parent.name === 'layer' && 
          (rule.parent.params === 'utilities' || rule.parent.params === 'components')) {
        
        // Process each selector
        rule.selector = rule.selector
          .split(',')
          .map(selector => {
            return selector.trim()
              // Add prefix to class selectors that don't already have it
              .replace(/\.(?!mbx-)/g, '.mbx-')
              // Handle responsive modifiers
              .replace(/\.mbx-(\w+)\\:/g, '.mbx-$1\\:')
              // Handle pseudo-class modifiers (hover, focus, etc.)
              .replace(/\.mbx-(\w+)\\:/g, '.mbx-$1\\:')
          })
          .join(', ');
      }
    }
  };
};

plugin.postcss = true;
module.exports = plugin;